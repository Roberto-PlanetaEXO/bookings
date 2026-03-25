<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoPlugin {
    const MENU_SLUG = 'planeta-exo';

    public static function init() {
        PlanetaExoACFUserFields::init();
        PlanetaExoCartLink::init();
        PlanetaExoCampaignColumns::init();
        // Debug desativado — reativar se precisar: PlanetaExoDebug::init(); PlanetaExoDebugSave::init();
        // PlanetaExoDebug::init();
        // PlanetaExoDebugSave::init();
        PlanetaExoACFFieldSave::init();
        PlanetaExoProductsNormalizer::init();
        PlanetaExoProductLinker::init();
        PlanetaExoMappingDebug::init();
        add_action('admin_menu', ['PlanetaExoAdminMenu', 'register'], 11);
        add_action( 'woocommerce_checkout_create_order_line_item', [ 'PlanetaExoOrderMeta', 'copy_campaign_id_to_order_item' ], 10, 4 );
        add_action( 'woocommerce_checkout_create_order', [ 'PlanetaExoOrderMeta', 'add_id_cartlink_to_order' ], 10, 2 );
        add_action( 'woocommerce_checkout_order_created', [ 'PlanetaExoOrderMeta', 'ensure_id_cartlink_after_order_created' ], 10, 1 );
        add_action('admin_init', ['PlanetaExoPropostasPage', 'handle_clone_request']);
        add_action('admin_init', ['PlanetaExoPropostasPage', 'register_menu_highlight_hooks']);
        add_filter('manage_ic-campaign_posts_columns', ['PlanetaExoPropostasPage', 'add_clone_column']);
        add_action('manage_ic-campaign_posts_custom_column', ['PlanetaExoPropostasPage', 'render_clone_column'], 10, 2);
    }
}
