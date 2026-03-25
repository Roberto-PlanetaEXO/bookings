<?php
/**
 * Template Name: Checkout Clássico (Reserva)
 *
 * Versão alternativa do checkout — layout clássico baseado no planetaexo.com/trips.
 * Para ativar: crie uma página no WP, atribua este template e defina-a como
 * página de checkout em WooCommerce → Configurações → Avançado → URLs da Loja.
 *
 * O body recebe a classe extra "checkout-classico" para escopar o CSS
 * sem interferir no checkout principal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Adiciona body class exclusiva para escopar o CSS
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'checkout-classico';
    return $classes;
} );

get_header(); ?>

<div class="pxo-checkout-classico-wrap">
    <?php
    if ( class_exists( 'WooCommerce' ) ) {
        // Shortcode clássico do WooCommerce (não Blocks)
        echo do_shortcode( '[woocommerce_checkout]' );
    } else {
        echo '<p>WooCommerce não está ativo.</p>';
    }
    ?>
</div>

<?php get_footer(); ?>
