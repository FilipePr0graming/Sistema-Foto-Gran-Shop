<?php
/**
 * Public Login View
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:ital,wght@0,100..900;1,100..900&display=swap"
    rel="stylesheet">

<div class="sdpp-login-wrapper">
    <div class="sdpp-login-card">
        <div class="sdpp-login-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                <path
                    d="M512 144C520.8 144 528 151.2 528 160L528 416C528 424.8 520.8 432 512 432L192 432C183.2 432 176 424.8 176 416L176 160C176 151.2 183.2 144 192 144L512 144zM192 96C156.7 96 128 124.7 128 160L128 416C128 451.3 156.7 480 192 480L512 480C547.3 480 576 451.3 576 416L576 160C576 124.7 547.3 96 512 96L192 96zM272 208C272 190.3 257.7 176 240 176C222.3 176 208 190.3 208 208C208 225.7 222.3 240 240 240C257.7 240 272 225.7 272 208zM412.7 211.8C408.4 204.5 400.5 200 392 200C383.5 200 375.6 204.5 371.3 211.8L324.8 290.8L307.6 266.2C303.1 259.8 295.8 256 287.9 256C280 256 272.7 259.8 268.2 266.2L212.2 346.2C207.1 353.5 206.4 363.1 210.6 371C214.8 378.9 223.1 384 232 384L472 384C480.6 384 488.6 379.4 492.8 371.9C497 364.4 497 355.2 492.7 347.8L412.7 211.8zM80 216C80 202.7 69.3 192 56 192C42.7 192 32 202.7 32 216L32 512C32 547.3 60.7 576 96 576L456 576C469.3 576 480 565.3 480 552C480 538.7 469.3 528 456 528L96 528C87.2 528 80 520.8 80 512L80 216z" />
            </svg>
        </div>
        <h2>
            <?php _e('Acessar Editor', 'polaroids-customizadas'); ?>
        </h2>
        <p>
            <?php _e('Digite o código do seu pedido para começar.', 'polaroids-customizadas'); ?>
        </p>

        <form id="sdpp-login-form" class="sdpp-login-form">
            <div class="sdpp-form-group">
                <input type="text" id="order-id" name="order_id"
                    placeholder="<?php _e('Código do Pedido', 'polaroids-customizadas'); ?>" required>
            </div>

            <div id="sdpp-login-message" class="sdpp-login-message" style="display: none;"></div>

            <button type="submit" class="sdpp-btn sdpp-btn-primary sdpp-btn-block" id="login-btn">
                <?php _e('Acessar', 'polaroids-customizadas'); ?>
            </button>
        </form>
    </div>
</div>

<style>
    .sdpp-login-wrapper {
        min-height: 60vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: #f3f4f6;
        font-family: 'Inter Tight', sans-serif;
    }

    .sdpp-login-card {
        background: white;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        width: 100%;
        max-width: 400px;
        text-align: center;
    }

    .sdpp-login-icon {
        margin-bottom: 24px;
        display: flex;
        justify-content: center;
    }

    .sdpp-login-icon svg {
        width: 64px;
        height: 64px;
        fill: #ff732e;
    }

    .sdpp-login-card h2 {
        margin: 0 0 8px;
        color: #111827;
        font-size: 24px;
        font-weight: 700;
        font-family: 'Inter Tight', sans-serif;
    }

    .sdpp-login-card p {
        margin: 0 0 24px;
        color: #6b7280;
    }

    .sdpp-form-group {
        margin-bottom: 20px;
    }

    .sdpp-form-group input {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.2s;
    }

    .sdpp-form-group input:focus {
        border-color: #ff732e;
        outline: none;
    }

    .sdpp-btn {
        padding: 12px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: transform 0.1s;
        font-family: 'Inter Tight', sans-serif;
    }

    .sdpp-btn-block {
        width: 100%;
    }

    .sdpp-btn-primary {
        background: #ff732e;
        color: white;
    }

    .sdpp-btn-primary:active {
        transform: scale(0.98);
    }

    .sdpp-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .sdpp-login-message {
        margin-bottom: 16px;
        padding: 10px;
        border-radius: 6px;
        font-size: 14px;
    }

    .sdpp-message-error {
        background: #fee2e2;
        color: #ef4444;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        $('#sdpp-login-form').on('submit', function (e) {
            e.preventDefault();

            const btn = $('#login-btn');
            const msg = $('#sdpp-login-message');
            const input = $('#order-id');

            btn.prop('disabled', true).text('Verificando...');
            msg.hide().removeClass('sdpp-message-error');

            $.ajax({
                url: '<?php echo admin_url("admin-ajax.php"); ?>',
                type: 'POST',
                data: {
                    action: 'sdpp_login',
                    order_id: input.val(),
                    nonce: '<?php echo wp_create_nonce("sdpp_login_nonce"); ?>'
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        msg.text(response.data.message || 'Erro ao acessar.').addClass('sdpp-message-error').show();
                        btn.prop('disabled', false).text('<?php _e("Acessar", "polaroids-customizadas"); ?>');
                    }
                },
                error: function () {
                    msg.text('Erro de conexão. Tente novamente.').addClass('sdpp-message-error').show();
                    btn.prop('disabled', false).text('<?php _e("Acessar", "polaroids-customizadas"); ?>');
                }
            });
        });
    });
</script>