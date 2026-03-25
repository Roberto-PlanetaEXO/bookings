/**
 * Checkout Recovery — workaround para erro 500/proxy no Pressable.
 * Quando o checkout exibe erro mas o pedido foi criado, tenta redirecionar para a página de confirmação.
 */
(function() {
    'use strict';

    if (typeof jQuery === 'undefined' || !window.pxoCheckoutRecovery) return;

    jQuery(document.body).on('checkout_error', function() {
        var $form = jQuery('form.checkout');
        var $block = $form.length ? $form : jQuery('body');

        // Oculta a mensagem de erro imediatamente
        jQuery('.woocommerce-error, .woocommerce-notice--error').hide();

        if ($block.length && $block.block) {
            $block.block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6 } });
        }

        jQuery.ajax({
            url: window.pxoCheckoutRecovery.ajaxUrl,
            type: 'POST',
            data: {
                action: 'pxo_checkout_recovery',
                nonce: window.pxoCheckoutRecovery.nonce
            },
            success: function(res) {
                if (res.success && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                    return;
                }
                jQuery('.woocommerce-error, .woocommerce-notice--error').show();
                if ($block.unblock) $block.unblock();
            },
            error: function() {
                jQuery('.woocommerce-error, .woocommerce-notice--error').show();
                if ($block.unblock) $block.unblock();
            }
        });
    });
})();
