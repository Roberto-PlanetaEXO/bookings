<?php
/**
 * Template Name: Checkout Novo (Clean)
 *
 * Template limpo baseado na versão funcional do planetaexo.com/trips
 * Estrutura simplificada sem complexidades desnecessárias.
 * 
 * Para ativar:
 * 1. Crie uma página no WordPress
 * 2. Atribua este template
 * 3. Defina como página de checkout em WooCommerce → Configurações → Avançado
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Adiciona body class para escopar o CSS
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'checkout-novo';
    $classes[] = 'checkout-clean';
    return $classes;
} );

get_header(); ?>

<div class="checkout-novo-wrapper">
    <?php
    if ( class_exists( 'WooCommerce' ) ) {
        // Shortcode clássico do WooCommerce
        echo do_shortcode( '[woocommerce_checkout]' );
    } else {
        echo '<p>WooCommerce não está ativo.</p>';
    }
    ?>
</div>

<?php get_footer(); ?>
