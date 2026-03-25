<?php
/**
 * Template Name: Checkout Clean (PlanetaExo)
 * 
 * Template criado do ZERO baseado no checkout funcional do planetaexo.com/trips
 * Usa EXATAMENTE o mesmo CSS que está funcionando na versão antiga.
 * 
 * Para usar:
 * 1. Criar página no WordPress
 * 2. Atribuir este template
 * 3. Definir como página de checkout em WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Adiciona body class
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'woocommerce-checkout';
    $classes[] = 'checkout-clean';
    return $classes;
} );

get_header(); ?>

<div class="checkout-clean-wrapper">
    <?php
    if ( class_exists( 'WooCommerce' ) ) {
        echo do_shortcode( '[woocommerce_checkout]' );
    } else {
        echo '<p>WooCommerce não está ativo.</p>';
    }
    ?>
</div>

<?php get_footer(); ?>
