/**
 * Admin JavaScript for Polaroids Customizadas
 */

(function ($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function () {
        initNewOrderForm();
        initOrderList();
        initCheckboxes();
        initMetricsPolling();
    });

    /**
     * Initialize Checkboxes (Select All)
     */
    function initCheckboxes() {
        const $selectAll = $('#cb-select-all-1');
        const $checkboxes = $('input[name="order_ids[]"]');
        const $bulkBar = $('#sdpp-bulk-actions');
        const $bulkCount = $('.sdpp-bulk-count');

        if (!$selectAll.length) return;

        function updateBulkBar() {
            const checkedCount = $('input[name="order_ids[]"]:checked').length;
            if (checkedCount > 0) {
                $bulkCount.text(checkedCount + (checkedCount === 1 ? ' selecionado' : ' selecionados'));
                $bulkBar.fadeIn(200);
            } else {
                $bulkBar.fadeOut(200);
            }
        }

        // Master toggle
        $selectAll.on('change', function () {
            const isChecked = $(this).is(':checked');
            $checkboxes.prop('checked', isChecked);
            updateBulkBar();
        });

        // Individual toggle
        $checkboxes.on('change', function () {
            const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            $selectAll.prop('checked', allChecked);
            updateBulkBar();
        });

        // Bulk Delete
        $('#bulk-delete-btn').on('click', function () {
            const ids = $('input[name="order_ids[]"]:checked').map(function () { return $(this).val(); }).get();
            if (ids.length === 0) return;

            if (!confirm('Tem certeza que deseja excluir ' + ids.length + ' pedidos selecionados?')) return;

            bulkAction('sdpp_bulk_delete', { order_ids: ids }, $(this));
        });

        // Bulk Print (ZIP)
        $('#bulk-print-btn').on('click', function () {
            const ids = $('input[name="order_ids[]"]:checked').map(function () { return $(this).val(); }).get();
            if (ids.length === 0) return;

            if (ids.length > 5) {
                alert('Selecione no máximo 5 pedidos por vez para imprimir.');
                return;
            }

            bulkAction('sdpp_bulk_generate_zip', { order_ids: ids }, $(this));
        });
    }

    /**
     * Generic Bulk Action Handler
     */
    function bulkAction(action, data, $btn) {
        const $icon = $btn.find('.dashicons');
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Processando...');

        if (action === 'sdpp_bulk_generate_zip') {
            bulkGenerateZip(data, $btn, originalHtml);
            return;
        }

        $.post(sdppAdmin.ajaxUrl, {
            action: action,
            nonce: sdppAdmin.nonce,
            ...data
        })
            .done(function (response) {
                if (response.success) {
                    if (response.data.url) {
                        // It's a file download
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.setAttribute('download', '');
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        // Success message
                        alert(response.data.message || 'Operação realizada com sucesso');

                        // If it was a delete action, update metrics instead of full reload?
                        // Actually, location.reload() is used here. 
                        // If we want to be truly modern, we'd update the table too.
                        // But the user specifically asked for "números trocarem em tempo real sem precisar atualizar a página".
                        if (action === 'sdpp_bulk_delete') {
                            updateMetrics();
                            location.reload(); // Still reloading for table sync for now
                        } else {
                            location.reload();
                        }
                    }
                } else {
                    alert(response.data?.message || 'Erro ao realizar operação');
                }
            })
            .fail(function (xhr) {
                let msg = 'Erro de conexão';
                if (xhr && xhr.status) {
                    msg += ' (' + xhr.status + ')';
                }
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg += ': ' + xhr.responseJSON.data.message;
                }
                alert(msg);
            })
            .always(function () {
                $btn.prop('disabled', false).html(originalHtml);
            });
    }

    function bulkGenerateZip(data, $btn, originalHtml) {
        let jobId = null;
        let done = false;
        let attempts = 0;
        const maxAttempts = 900;

        function downloadUrl(url) {
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', '');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function failAndReset(message) {
            done = true;
            alert(message || 'Erro de conexão');
            $btn.prop('disabled', false).html(originalHtml);
        }

        function tick() {
            if (done) return;
            attempts++;
            if (attempts > maxAttempts) {
                failAndReset('Tempo excedido ao gerar o ZIP em massa. Tente novamente.');
                return;
            }

            const payload = {
                action: 'sdpp_bulk_generate_zip',
                nonce: sdppAdmin.nonce,
                ...data
            };
            if (jobId) {
                payload.job_id = jobId;
            }

            $.post(sdppAdmin.ajaxUrl, payload)
                .done(function (response) {
                    if (!response || !response.success) {
                        failAndReset(response?.data?.message || 'Erro ao realizar operação');
                        return;
                    }

                    if (response.data && response.data.job_id) {
                        jobId = response.data.job_id;
                    }

                    const percent = parseInt(response.data?.percent || 0, 10);
                    if (!isNaN(percent)) {
                        $btn.html('<span class="dashicons dashicons-update spin"></span> Processando... ' + percent + '%');
                    }

                    const status = String(response.data?.status || 'running');
                    if (status === 'done' && response.data && response.data.url) {
                        done = true;
                        downloadUrl(response.data.url);
                        $btn.prop('disabled', false).html(originalHtml);
                        return;
                    }

                    setTimeout(tick, 350);
                })
                .fail(function (xhr) {
                    let msg = 'Erro de conexão';
                    if (xhr && xhr.status) {
                        msg += ' (' + xhr.status + ')';
                    }
                    if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg += ': ' + xhr.responseJSON.data.message;
                    }
                    failAndReset(msg);
                });
        }

        tick();
    }

    /**
     * Initialize New Order Form
     */
    function initNewOrderForm() {
        const $form = $('#sdpp-new-order-form');
        if (!$form.length) return;

        // Store selection - update footer preview
        $('select[name="store"]').on('change', function () {
            const $selected = $(this).find(':selected');
            const footerColor = $selected.data('footer-color');
            const storeName = $selected.text();

            $('#footer-store-name').text(storeName);
            // Also update the color text/indicator if we want, or just the text
            $('.sdpp-footer-example').css('color', footerColor);
            $('.sdpp-color-indicator').text(footerColor); // Update debug/info text if present
        });

        // Order ID - update footer preview
        $('#order_id').on('input', function () {
            const orderId = $(this).val() || 'XXXXXXXXX';
            $('#footer-order-id').text(orderId);
        });

        // Custom quantity toggle
        $('input[name="photo_quantity"]').on('change', function () {
            const isCustom = $(this).val() === 'custom';
            $('.sdpp-custom-quantity').toggle(isCustom);
            if (isCustom) {
                $('#custom_quantity').focus();
            }
        });

        // Grid type change
        $('select[name="grid_type"]').on('change', function () {
            // Logic removed to allow any quantity for 2x3 grid
        });

        // Border toggle - show/hide font section
        $('#has_border').on('change', function () {
            const hasBorder = $(this).is(':checked');
            $('#font-section').slideToggle(hasBorder);
        });

        // Form submission
        $form.on('submit', function (e) {
            e.preventDefault();
            submitOrder();
        });

        // Modal handlers
        $('#copy-id-btn').on('click', function () {
            copyOrderId();
        });

        $('#create-another-btn').on('click', function () {
            $('#success-modal').hide();
            $form[0].reset();
            // Reset UI to initial state
            $('.sdpp-custom-quantity').hide();
            $('#font-section').hide();
            $('#footer-store-name').text('Gran Shop');
            $('#footer-order-id').text('XXXXXXXXX');
            $('.sdpp-footer-example').css('color', '#000000');
        });
    }

    /**
     * Submit order via AJAX
     */
    function submitOrder() {
        const $form = $('#sdpp-new-order-form');
        const $submitBtn = $('#submit-order');

        // Validate
        const orderId = $('#order_id').val().trim();
        if (!orderId) {
            alert(sdppAdmin.i18n?.orderIdRequired || 'O ID do Pedido é obrigatório');
            return;
        }

        // Get photo quantity
        let photoQuantity = $('input[name="photo_quantity"]:checked').val();
        if (photoQuantity === 'custom') {
            photoQuantity = $('#custom_quantity').val();
        }

        if (!photoQuantity || photoQuantity < 1) {
            alert(sdppAdmin.i18n?.quantityRequired || 'A quantidade de fotos é obrigatória');
            return;
        }

        // Disable button
        $submitBtn.prop('disabled', true).text('Criando...');

        // Prepare data
        const data = {
            action: 'sdpp_create_order',
            nonce: sdppAdmin.nonce,
            order_id: orderId,
            store: $('select[name="store"]').val(),
            photo_quantity: photoQuantity,
            grid_type: $('select[name="grid_type"]').val(),
            has_border: $('#has_border').is(':checked') ? 1 : 0,
            has_magnet: $('input[name="has_magnet"]').is(':checked') ? 1 : 0,
            has_clip: $('input[name="has_clip"]').is(':checked') ? 1 : 0,
            has_twine: $('input[name="has_twine"]').is(':checked') ? 1 : 0,
            has_frame: $('input[name="has_frame"]').is(':checked') ? 1 : 0,
            customer_name: $('#customer_name').val()
        };

        $.post(sdppAdmin.ajaxUrl, data)
            .done(function (response) {
                if (response.success) {
                    // Show Order ID
                    $('#created-order-id').val(response.data.order.order_id);
                    $('#success-modal').show();
                } else {
                    alert(response.data?.message || 'Falha ao criar pedido');
                }
            })
            .fail(function () {
                alert('Ocorreu um erro. Por favor, tente novamente.');
            })
            .always(function () {
                $submitBtn.prop('disabled', false).text('Criar Pedido');
            });
    }

    /**
     * Copy order ID to clipboard
     */
    function copyOrderId() {
        const $input = $('#created-order-id');
        $input.select();
        document.execCommand('copy');

        const $btn = $('#copy-id-btn');
        const originalHtml = $btn.html();
        $btn.html('<span class="dashicons dashicons-yes"></span> Copiado!');

        setTimeout(function () {
            $btn.html(originalHtml);
        }, 2000);
    }

    /**
     * Initialize Order List
     */
    function initOrderList() {
        const $table = $('.sdpp-orders-table');
        if (!$table.length) return;

        // View Order Modal
        $table.on('click', '.sdpp-view-order', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const orderData = $row.data('order');
            if (orderData) {
                openViewModal(orderData);
            }
        });

        // Edit Order Modal
        $table.on('click', '.sdpp-edit-order', function (e) {
            e.preventDefault();
            const $row = $(this).closest('tr');
            const orderData = $row.data('order');
            if (orderData) {
                openEditModal(orderData);
            }
        });

        // Copy ID
        $table.on('click', '.sdpp-copy-id', function (e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');

            // Copy to clipboard
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(orderId).select();
            document.execCommand('copy');
            $temp.remove();

            // Visual feedback
            const $icon = $(this).find('.dashicons');
            const originalClass = $icon.attr('class');

            $icon.removeClass('dashicons-admin-page').addClass('dashicons-yes');
            setTimeout(function () {
                $icon.attr('class', originalClass);
            }, 1000);
        });

        // Generate PNG
        $table.on('click', '.sdpp-generate-png', function (e) {
            e.preventDefault();
            const orderId = $(this).data('order-id');
            generatePng(orderId, $(this));
        });

        // Delete order
        $table.on('click', '.sdpp-delete-order', function (e) {
            e.preventDefault();
            if (!confirm('Tem certeza que deseja excluir este pedido?')) return;

            const orderId = $(this).data('order-id');
            deleteOrder(orderId, $(this).closest('tr'));
        });

        // Modal Close Handlers
        $('.sdpp-close-modal, .sdpp-modal').on('click', function (e) {
            if (e.target === this) {
                $('.sdpp-modal').fadeOut();
            }
        });

        // Edit Form Submit
        $('#sdpp-edit-form').on('submit', function (e) {
            e.preventDefault();
            submitEditForm($(this));
        });
    }

    /**
     * Open View Modal
     */
    function openViewModal(order) {
        const $modal = $('#sdpp-view-modal');
        const $content = $('#sdpp-view-content');

        // Build Details HTML
        let html = `
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">ID do Pedido:</span>
                <span class="sdpp-detail-value">
                    ${order.order_id} 
                    <a href="#" class="sdpp-action-icon sdpp-copy-id" data-order-id="${order.order_id}" title="Copiar ID"><span class="dashicons dashicons-admin-page"></span></a>
                </span>
            </div>
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Nome do Cliente:</span>
                <span class="sdpp-detail-value">${order.customer_name || '—'}</span>
            </div>
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Email:</span>
                <span class="sdpp-detail-value">${order.customer_email || '—'}</span>
            </div>
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Status:</span>
                <span class="sdpp-detail-value">${order.status}</span>
            </div>
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Loja:</span>
                <span class="sdpp-detail-value">${order.store}</span>
            </div>
             <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Grid Escolhido:</span>
                <span class="sdpp-detail-value">${order.grid_type || '3x3'}</span>
            </div>
            <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Quantidade de Fotos:</span>
                <span class="sdpp-detail-value">${order.photo_quantity}</span>
            </div>
             <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Borda:</span>
                <span class="sdpp-detail-value">${order.has_border == 1 ? 'Sim' : 'Não'}</span>
            </div>
             <div class="sdpp-detail-row">
                <span class="sdpp-detail-label">Data de Criação:</span>
                <span class="sdpp-detail-value">${order.created_at}</span>
            </div>
        `;

        $content.html(html);
        $modal.fadeIn();
    }

    /**
     * Open Edit Modal
     */
    function openEditModal(order) {
        const $modal = $('#sdpp-edit-modal');

        // Populate inputs
        $('#edit-order-db-id').val(order.id);
        $('#edit-customer-name').val(order.customer_name);
        $('#edit-customer-email').val(order.customer_email);
        $('#edit-status').val(order.status);
        $('#edit-store').val(order.store);

        $modal.fadeIn();
    }

    /**
     * Submit Edit Form
     */
    function submitEditForm($form) {
        const $btn = $form.find('button[type="submit"]');
        const originalText = $btn.text();

        $btn.prop('disabled', true).text('Salvando...');

        $.post(sdppAdmin.ajaxUrl, $form.serialize())
            .done(function (response) {
                if (response.success) {
                    $('.sdpp-modal').fadeOut();
                    // Reload page to show updates (simplest way to update table correctly)
                    location.reload();
                } else {
                    alert(response.data?.message || 'Falha ao atualizar pedido');
                    $btn.prop('disabled', false).text(originalText);
                }
            })
            .fail(function () {
                alert('Erro de conexão');
                $btn.prop('disabled', false).text(originalText);
            });
    }

    /**
     * Generate PNG for order
     */
    function generatePng(orderId, $btn) {
        const $icon = $btn.find('.dashicons');
        $icon.removeClass('dashicons-printer').addClass('dashicons-update spin');

        const progress = startPngProgressPolling(orderId, $btn);

        $.post(sdppAdmin.ajaxUrl, {
            action: 'sdpp_generate_png',
            nonce: sdppAdmin.nonce,
            order_id: orderId
        })
            .done(function (response) {
                if (response.success) {
                    if (progress && typeof progress.complete === 'function') {
                        progress.complete();
                    }
                    // Update UI Status if returned
                    if (response.data.new_status && response.data.new_status_label) {
                        const $row = $btn.closest('tr');
                        // Find status dot
                        const $statusMark = $row.find('.sdpp-status-dot');
                        if ($statusMark.length) {
                            $statusMark
                                .removeClass('status-pending status-photos_uploaded status-completed status-trash status-cancelled')
                                .addClass('status-' + response.data.new_status)
                                .text(response.data.new_status_label)
                                .attr('title', response.data.new_status_label);
                        }
                    }

                    const url = response.data.url;
                    // Trigger automatic download
                    const link = document.createElement('a');
                    link.href = url;
                    // download attribute helps trigger download instead of opening
                    link.setAttribute('download', '');
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                } else {
                    alert(response.data?.message || 'Falha ao gerar PNG');
                }
            })
            .fail(function () {
                alert('Ocorreu um erro. Por favor, tente novamente.');
            })
            .always(function () {
                if (progress && typeof progress.stop === 'function') {
                    progress.stop();
                }
                $icon.removeClass('dashicons-update spin').addClass('dashicons-printer');
            });
    }

    function startPngProgressPolling(orderId, $btn) {
        const $row = $btn.closest('tr');
        if (!$row.length) {
            return null;
        }

        let $label = $row.find('.sdpp-png-progress[data-order-id="' + orderId + '"]');
        if (!$label.length) {
            $label = $('<span class="sdpp-png-progress" data-order-id="' + orderId + '" style="margin-left:6px; font-weight:600;"></span>');
            $btn.after($label);
        }

        $label.text('0%');

        let stopped = false;
        let timer = null;
        let lastPercent = -1;

        function tick() {
            if (stopped) {
                return;
            }

            $.post(sdppAdmin.ajaxUrl, {
                action: 'sdpp_get_png_progress',
                nonce: sdppAdmin.nonce,
                order_id: orderId
            }).done(function (response) {
                if (!response || !response.success || !response.data) {
                    return;
                }

                const percent = parseInt(response.data.percent || 0, 10);
                const status = String(response.data.status || 'idle');

                if (percent !== lastPercent) {
                    lastPercent = percent;
                    $label.text(percent + '%');
                }

                if (status === 'done' || percent >= 100) {
                    $label.text('100%');
                    stop();
                }

                if (status === 'error') {
                    stop();
                }
            });

            timer = setTimeout(tick, 800);
        }

        function stop() {
            stopped = true;
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        }

        function complete() {
            $label.text('100%');
        }

        tick();
        return { stop, complete };
    }

    /**
     * Delete order
     */
    function deleteOrder(orderId, $row) {
        $.post(sdppAdmin.ajaxUrl, {
            action: 'sdpp_delete_order',
            nonce: sdppAdmin.nonce,
            order_id: orderId
        })
            .done(function (response) {
                if (response.success) {
                    // Start fade out but also trigger reload to update counts ideally, 
                    // or just remove row if we want to be fast. 
                    // Since we have counts at the top which will be stale, reload is better but slower.
                    // For now, remove row for smooth UX and let user reload if they care about exact count sync.
                    $row.fadeOut(300, function () {
                        $(this).remove();
                        updateMetrics(); // Update counts after smooth removal
                    });
                } else {
                    alert(response.data?.message || 'Falha ao excluir pedido');
                }
            })
            .fail(function () {
                alert('Ocorreu um erro. Por favor, tente novamente.');
            });
    }

    /**
     * Metrics Real-time Updates
     */
    function initMetricsPolling() {
        if (!$('.sdpp-metrics-grid').length) return;

        // Initial update
        updateMetrics();

        // Polling every 30 seconds
        setInterval(updateMetrics, 30000);
    }

    function updateMetrics() {
        const $metrics = {
            all: $('#metric-all'),
            pending: $('#metric-pending'),
            uploaded: $('#metric-uploaded'),
            completed: $('#metric-completed'),
            trash: $('#metric-trash')
        };

        if (!$metrics.all.length) return;

        $.post(sdppAdmin.ajaxUrl, {
            action: 'sdpp_get_metrics',
            nonce: sdppAdmin.nonce
        })
            .done(function (response) {
                if (response.success) {
                    const data = response.data;

                    // Update each metric with a subtle pulse effect if changed
                    Object.keys(data).forEach(key => {
                        const $el = $metrics[key];
                        if ($el && $el.text() !== data[key]) {
                            $el.fadeOut(200, function () {
                                $(this).text(data[key]).fadeIn(200);
                            });
                        }
                    });
                }
            });
    }

    /**
     * Initialize Email Settings Form
     */
    function initEmailSettingsForm() {
        const $form = $('#sdpp-email-settings-form');
        if (!$form.length) return;

        // Toggle SMTP Visibility
        $('input[name="enable_smtp"]').on('change', function () {
            if ($(this).is(':checked')) {
                $('.sdpp-smtp-settings').slideDown();
                $('.sdpp-smtp-test').slideDown();
            } else {
                $('.sdpp-smtp-settings').slideUp();
                $('.sdpp-smtp-test').slideUp();
            }
        });

        // Test Email Handler
        $('#sdpp-test-email-btn').on('click', function () {
            const $btn = $(this);
            const $emailInput = $('#test_email_dest');
            const $status = $('#sdpp-test-email-status');
            const email = $emailInput.val().trim();
            const originalHtml = $btn.html();

            if (!email) {
                alert('Por favor, digite um email para teste.');
                $emailInput.focus();
                return;
            }

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Enviando...');
            $status.html('');

            $.post(sdppAdmin.ajaxUrl, {
                action: 'sdpp_send_test_email',
                nonce: sdppAdmin.nonce,
                email: email
            })
                .done(function (response) {
                    if (response.success) {
                        $status.html('<div style="color: green; font-weight: bold;"><span class="dashicons dashicons-yes"></span> ' + response.data.message + '</div>');
                    } else {
                        $status.html('<div style="color: red; font-weight: bold;"><span class="dashicons dashicons-no"></span> ' + (response.data.message || 'Erro ao enviar') + '</div>');
                    }
                })
                .fail(function () {
                    $status.html('<div style="color: red; font-weight: bold;">Erro de conexão</div>');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(originalHtml);
                });
        });

        $form.on('submit', function (e) {
            e.preventDefault();

            const $btn = $('#save-email-settings-btn');
            const $status = $('#email-settings-status');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Salvando...');
            $status.text('').removeClass('error success');

            $.post(sdppAdmin.ajaxUrl, {
                action: 'sdpp_save_email_settings',
                nonce: $form.find('#sdpp_email_nonce').val(),
                enable_notifications: $form.find('input[name="enable_notifications"]').is(':checked') ? 1 : 0,
                admin_email: $form.find('input[name="admin_email"]').val(),
                admin_email_subject: $form.find('input[name="admin_email_subject"]').val(),
                admin_email_body: $form.find('textarea[name="admin_email_body"]').val(),
                customer_email_subject: $form.find('input[name="customer_email_subject"]').val(),
                customer_email_body: $form.find('textarea[name="customer_email_body"]').val(),
                // SMTP Settings
                enable_smtp: $form.find('input[name="enable_smtp"]').is(':checked') ? 1 : 0,
                smtp_host: $form.find('input[name="smtp_host"]').val(),
                smtp_port: $form.find('input[name="smtp_port"]').val(),
                smtp_user: $form.find('input[name="smtp_user"]').val(),
                smtp_pass: $form.find('input[name="smtp_pass"]').val(),
                smtp_from_name: $form.find('input[name="smtp_from_name"]').val(),
                smtp_encryption: $form.find('select[name="smtp_encryption"]').val()
            })
                .done(function (response) {
                    if (response.success) {
                        $status.addClass('success').css('color', 'green').text('✓ ' + response.data.message);
                    } else {
                        $status.addClass('error').css('color', 'red').text('✗ ' + (response.data?.message || 'Erro ao salvar configurações'));
                    }
                })
                .fail(function () {
                    $status.addClass('error').css('color', 'red').text('✗ Erro de conexão');
                })
                .always(function () {
                    $btn.prop('disabled', false).text(originalText);
                });
        });
    }

    // Initialize email settings on document ready
    $(document).ready(function () {
        initEmailSettingsForm();
        initBackupRestore();
    });

    /**
     * Initialize Backup & Restore Handlers
     */
    function initBackupRestore() {
        // Export
        $('#sdpp-export-btn').on('click', function () {
            const $btn = $(this);
            const $status = $('#sdpp-export-status');
            const originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Gerando Backup...');
            $status.text('').removeClass('error success');

            $.post(sdppAdmin.ajaxUrl, {
                action: 'sdpp_export_backup',
                nonce: sdppAdmin.nonce
            })
                .done(function (response) {
                    if (response.success) {
                        $status.addClass('success').css('color', 'green').text(response.data.message);

                        // Trigger download
                        if (response.data.url) {
                            setTimeout(function () {
                                window.location.href = response.data.url;
                            }, 1000);
                        }
                    } else {
                        $status.addClass('error').css('color', 'red').text('Erro: ' + (response.data?.message || 'Falha ao gerar backup'));
                    }
                })
                .fail(function () {
                    $status.addClass('error').css('color', 'red').text('Erro de conexão ao servidor.');
                })
                .always(function () {
                    $btn.prop('disabled', false).html(originalHtml);
                });
        });

        // Import - Select File
        $('#sdpp-select-file-btn').on('click', function () {
            $('#sdpp-import-file').click();
        });

        $('#sdpp-import-file').on('change', function () {
            const file = this.files[0];
            if (file) {
                $('#sdpp-file-name').text(file.name);
                $('#sdpp-import-btn').prop('disabled', false);
            } else {
                $('#sdpp-file-name').text('');
                $('#sdpp-import-btn').prop('disabled', true);
            }
        });

        // Import - Execute
        $('#sdpp-import-btn').on('click', function () {
            const $btn = $(this);
            const $status = $('#sdpp-import-status');
            const originalText = $btn.text();

            const fileInput = $('#sdpp-import-file')[0];
            if (!fileInput.files.length) return;

            if (!confirm('ATENÇÃO: A importação irá adicionar novos pedidos e fotos e atualizar os existentes. Recomendamos fazer um backup antes. Deseja continuar?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'sdpp_import_backup');
            formData.append('nonce', sdppAdmin.nonce);
            formData.append('backup_file', fileInput.files[0]);

            $btn.prop('disabled', true).text('Restaurando... (isso pode demorar)');
            $status.text('').removeClass('error success');

            $.ajax({
                url: sdppAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
                .done(function (response) {
                    if (response.success) {
                        $status.addClass('success').css('color', 'green').text(response.data.message);
                        // Clear input
                        $('#sdpp-import-file').val('');
                        $('#sdpp-file-name').text('');
                        $btn.prop('disabled', true);
                    } else {
                        $status.addClass('error').css('color', 'red').text('Erro: ' + (response.data?.message || 'Falha na importação'));
                    }
                })
                .fail(function () {
                    $status.addClass('error').css('color', 'red').text('Erro de conexão ou tempo limite excedido.');
                })
                .always(function () {
                    $btn.text(originalText);
                    // Keep disabled if success to prevent double click, re-enable if fail done via file change mostly
                    if ($status.hasClass('error')) {
                        $btn.prop('disabled', false);
                    }
                });
        });
    }

    // Add spin animation for loading state
    $('<style>.dashicons.spin { animation: sdpp-spin 1s infinite linear; } @keyframes sdpp-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);
