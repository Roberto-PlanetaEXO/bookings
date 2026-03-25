<?php
/**
 * Plugin Name: Planeta Exo Unificado
 * Description: Versão unificada e organizada do menu Planeta Exo (Propostas, Visualizar E-mails e Pedidos sem Proposta).
 * Version: 1.1.0
 * Author: Migração Interna
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/planetaexo-i18n.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-plugin.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-admin-menu.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-propostas-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-acf-validation.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-acf-user-fields.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-email-preview-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-orders-without-proposal-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-email-acf-debug.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-order-meta.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-proposta-display.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-cart-link.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-campaign-columns.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-debug.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-debug-save.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-acf-field-save.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-products-normalizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-product-linker.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-mapping-debug.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-planetaexo-email-override.php';

PlanetaExoPlugin::init();
PlanetaExoEmailOverride::init();
