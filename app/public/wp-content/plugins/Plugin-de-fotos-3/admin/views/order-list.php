<?php
/**
 * Admin Order List View - Redesigned
 */

if (!defined('ABSPATH')) {
    exit;
}

$database = new SDPP_Database();

// Handle actions
if (isset($_POST['action']) && isset($_POST['order_ids']) && check_admin_referer('sdpp_bulk_action', 'sdpp_nonce')) {
    $order_ids = array_map('intval', $_POST['order_ids']);

    if ($_POST['action'] === 'delete') {
        foreach ($order_ids as $id) {
            $database->delete_order($id);
        }
    }
}

// Handle filters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$store_filter = isset($_GET['store']) ? sanitize_text_field($_GET['store']) : '';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Pagination
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Build query args
$args = array(
    'status' => $status_filter,
    'store' => $store_filter,
    'search' => $search_query,
    'limit' => $per_page,
    'offset' => $offset
);

// Get orders
$orders = $database->get_orders($args);

// Get counts for dashboard cards
$count_all = $database->get_orders_count(array('status' => '')); // All active
$count_pending = $database->get_orders_count(array('status' => 'pending'));
$count_uploaded = $database->get_orders_count(array('status' => 'photos_uploaded'));
$count_completed = $database->get_orders_count(array('status' => 'completed'));

// For trash count, we need to bypass the default exclusion
$count_trash = $database->get_orders_count(array('status' => 'trash'));


// Total stats for current view (for pagination)
$total_orders = $database->get_orders_count($args);
$total_pages = ceil($total_orders / $per_page);

$stores = SDPP_Plugin::get_instance()->get_stores_config();
?>

<div class="wrap sdpp-admin-wrap">

    <!-- Dashboard Header -->
    <div class="sdpp-dashboard-header">
        <h1 class="wp-heading-inline"><?php _e('Lista de Pedidos', 'polaroids-customizadas'); ?></h1>
        <div class="sdpp-breadcrumbs">
            <span>Dashboard</span> <span class="sep">></span> <span>Lista de Pedidos</span>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="sdpp-metrics-grid">
        <!-- Total -->
        <a href="?page=polaroids-customizadas" class="sdpp-metric-card">
            <div class="sdpp-metric-info">
                <span class="sdpp-metric-label"><?php _e('TOTAL DE PEDIDOS', 'polaroids-customizadas'); ?></span>
                <span class="sdpp-metric-value" id="metric-all"><?php echo number_format_i18n($count_all); ?></span>
            </div>
            <div class="sdpp-metric-icon icon-blue">
                <span class="dashicons dashicons-products"></span>
            </div>
        </a>

        <!-- Pending -->
        <a href="?page=polaroids-customizadas&status=pending" class="sdpp-metric-card">
            <div class="sdpp-metric-info">
                <span class="sdpp-metric-label"><?php _e('PENDENTES', 'polaroids-customizadas'); ?></span>
                <span class="sdpp-metric-value"
                    id="metric-pending"><?php echo number_format_i18n($count_pending); ?></span>
            </div>
            <div class="sdpp-metric-icon icon-yellow">
                <span class="dashicons dashicons-clock"></span>
            </div>
        </a>

        <!-- Photos Uploaded (In Progress/Processing) -->
        <a href="?page=polaroids-customizadas&status=photos_uploaded" class="sdpp-metric-card">
            <div class="sdpp-metric-info">
                <span class="sdpp-metric-label"><?php _e('FOTOS ENVIADAS', 'polaroids-customizadas'); ?></span>
                <span class="sdpp-metric-value"
                    id="metric-uploaded"><?php echo number_format_i18n($count_uploaded); ?></span>
            </div>
            <div class="sdpp-metric-icon icon-purple">
                <span class="dashicons dashicons-images-alt2"></span>
            </div>
        </a>

        <!-- Completed -->
        <a href="?page=polaroids-customizadas&status=completed" class="sdpp-metric-card">
            <div class="sdpp-metric-info">
                <span class="sdpp-metric-label"><?php _e('CONCLUÍDOS', 'polaroids-customizadas'); ?></span>
                <span class="sdpp-metric-value"
                    id="metric-completed"><?php echo number_format_i18n($count_completed); ?></span>
            </div>
            <div class="sdpp-metric-icon icon-green">
                <span class="dashicons dashicons-yes"></span>
            </div>
        </a>

        <!-- Trash -->
        <a href="?page=polaroids-customizadas&status=trash" class="sdpp-metric-card">
            <div class="sdpp-metric-info">
                <span class="sdpp-metric-label"><?php _e('LIXEIRA', 'polaroids-customizadas'); ?></span>
                <span class="sdpp-metric-value" id="metric-trash"><?php echo number_format_i18n($count_trash); ?></span>
            </div>
            <div class="sdpp-metric-icon icon-red">
                <span class="dashicons dashicons-trash"></span>
            </div>
        </a>
    </div>

    <!-- Actions Bar -->
    <div class="sdpp-actions-bar">
        <a href="<?php echo admin_url('admin.php?page=polaroids-new-order'); ?>" class="sdpp-button-primary">
            <span class="dashicons dashicons-plus"></span> <?php _e('NOVO PEDIDO', 'polaroids-customizadas'); ?>
        </a>

        <div class="sdpp-search-box">
            <form method="get">
                <input type="hidden" name="page" value="polaroids-customizadas">
                <span class="dashicons dashicons-search"></span>
                <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search...">
            </form>
        </div>

        <div id="sdpp-bulk-actions" class="sdpp-bulk-actions" style="display: none;">
            <span class="sdpp-bulk-count">0 selecionados</span>
            <button type="button" id="bulk-print-btn" class="sdpp-button-secondary">
                <span class="dashicons dashicons-printer"></span>
                <?php _e('Imprimir Selecionados', 'polaroids-customizadas'); ?>
            </button>
            <button type="button" id="bulk-delete-btn" class="sdpp-button-danger">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Excluir Selecionados', 'polaroids-customizadas'); ?>
            </button>
        </div>
    </div>

    <form method="post" id="sdpp-bulk-actions-form">
        <?php wp_nonce_field('sdpp_bulk_action', 'sdpp_nonce'); ?>
        <input type="hidden" name="action" id="bulk-action-input" value="">

        <!-- Orders Table -->
        <div class="sdpp-table-container">
            <table class="sdpp-orders-table">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="cb-select-all-1"></th>
                        <th><?php _e('ID do Pedido', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Cliente', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Loja', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Grid', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Fotos', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Status', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Data', 'polaroids-customizadas'); ?></th>
                        <th><?php _e('Ações', 'polaroids-customizadas'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="9" class="sdpp-no-orders">
                                <?php _e('Nenhum pedido encontrado.', 'polaroids-customizadas'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr data-order='<?php echo htmlspecialchars(json_encode($order), ENT_QUOTES, 'UTF-8'); ?>'>
                                <td class="check-column">
                                    <input type="checkbox" name="order_ids[]" value="<?php echo esc_attr($order->id); ?>">
                                </td>
                                <td class="column-order-id">
                                    <strong><?php echo esc_html($order->order_id); ?></strong>
                                    <a href="#" class="sdpp-action-icon sdpp-copy-id"
                                        data-order-id="<?php echo esc_attr($order->order_id); ?>"
                                        title="<?php _e('Copiar ID', 'polaroids-customizadas'); ?>">
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </a>
                                </td>
                                <td class="column-customer">
                                    <div class="customer-info">
                                        <span class="name"><?php echo esc_html($order->customer_name ?: '—'); ?></span>
                                        <?php if ($order->customer_email): ?>
                                            <span class="email"><?php echo esc_html($order->customer_email); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-store">
                                    <?php echo esc_html($stores[$order->store]['name'] ?? $order->store); ?>
                                </td>
                                <td class="column-grid">
                                    <?php echo esc_html($order->grid_type ?? '3x3'); ?>
                                </td>
                                <td class="column-photos">
                                    <?php echo esc_html($order->photo_quantity); ?>
                                </td>
                                <td class="column-status">
                                    <span class="sdpp-status-dot status-<?php echo esc_attr($order->status); ?>">
                                        <?php
                                        $status_labels = array(
                                            'pending' => __('Pendente', 'polaroids-customizadas'),
                                            'photos_uploaded' => __('(fotos enviadas)', 'polaroids-customizadas'),
                                            'completed' => __('Concluído', 'polaroids-customizadas'),
                                            'trash' => __('Lixeira', 'polaroids-customizadas'),
                                            'cancelled' => __('Cancelado', 'polaroids-customizadas')
                                        );
                                        echo esc_html($status_labels[$order->status] ?? $order->status);
                                        ?>
                                    </span>
                                </td>
                                <td class="column-date">
                                    <?php echo esc_html(date_i18n('d/m/Y', strtotime($order->created_at))); ?>
                                </td>
                                <td class="column-actions">
                                    <div class="sdpp-actions">
                                        <a href="#" class="sdpp-action-btn view sdpp-view-order" title="Ver Detalhes">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <a href="#" class="sdpp-action-btn edit sdpp-edit-order" title="Editar">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        <a href="#" class="sdpp-action-btn print sdpp-generate-png"
                                            data-order-id="<?php echo esc_attr($order->id); ?>" title="Imprimir (Gerar PNG)">
                                            <span class="dashicons dashicons-printer"></span>
                                        </a>
                                        <a href="#" class="sdpp-action-btn delete sdpp-delete-order"
                                            data-order-id="<?php echo esc_attr($order->id); ?>" title="Excluir">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="sdpp-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page
            ));
            ?>
        </div>
    <?php endif; ?>

    <!-- View Modal -->
    <div id="sdpp-view-modal" class="sdpp-modal">
        <div class="sdpp-modal-content">
            <span class="sdpp-close-modal">&times;</span>
            <h2><?php _e('Detalhes do Pedido', 'polaroids-customizadas'); ?></h2>
            <div id="sdpp-view-content">
                <!-- Content populated by JS -->
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="sdpp-edit-modal" class="sdpp-modal">
        <div class="sdpp-modal-content">
            <span class="sdpp-close-modal">&times;</span>
            <h2><?php _e('Editar Pedido', 'polaroids-customizadas'); ?></h2>
            <form id="sdpp-edit-form">
                <input type="hidden" name="action" value="sdpp_update_order">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('sdpp_admin_nonce'); ?>">
                <input type="hidden" name="id" id="edit-order-db-id">

                <div class="form-group">
                    <label><?php _e('Nome do Cliente', 'polaroids-customizadas'); ?></label>
                    <input type="text" name="customer_name" id="edit-customer-name">
                </div>

                <div class="form-group">
                    <label><?php _e('Email', 'polaroids-customizadas'); ?></label>
                    <input type="email" name="customer_email" id="edit-customer-email" readonly>
                </div>

                <div class="form-group">
                    <label><?php _e('Status', 'polaroids-customizadas'); ?></label>
                    <select name="status" id="edit-status">
                        <option value="pending"><?php _e('Pendente', 'polaroids-customizadas'); ?></option>
                        <option value="photos_uploaded"><?php _e('(fotos enviadas)', 'polaroids-customizadas'); ?>
                        </option>
                        <option value="completed"><?php _e('Concluído', 'polaroids-customizadas'); ?></option>
                        <option value="cancelled"><?php _e('Cancelado', 'polaroids-customizadas'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label><?php _e('Loja', 'polaroids-customizadas'); ?></label>
                    <select name="store" id="edit-store">
                        <?php foreach ($stores as $key => $store): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($store['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit"
                        class="button button-primary"><?php _e('Salvar Alterações', 'polaroids-customizadas'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>