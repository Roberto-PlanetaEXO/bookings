<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoAdminMenu {
    public static function register() {
        // O menu "Planeta Exo" principal é criado pelo plugin Cart Link for WooCommerce
        // (slug: 'planeta-exo'). O plugin Unificado apenas acrescenta submenus extras.

        add_submenu_page(
            PlanetaExoPlugin::MENU_SLUG,
            'Visualizar E-mails WooCommerce',
            'Visualizar E-mails',
            'manage_options',
            'visualizar-emails-woocommerce',
            ['PlanetaExoEmailPreviewPage', 'render']
        );

        add_submenu_page(
            PlanetaExoPlugin::MENU_SLUG,
            'Visualizar Pedidos sem uma Proposta Amarrada',
            'Pedidos sem Proposta',
            'manage_options',
            'visualizar-pedidos-sem-cartlink',
            ['PlanetaExoOrdersWithoutProposalPage', 'render']
        );

        add_submenu_page(
            PlanetaExoPlugin::MENU_SLUG,
            'Debug: ACF no E-mail',
            'Debug ACF E-mail',
            'manage_woocommerce',
            'planetaexo-email-acf-debug',
            ['PlanetaExoEmailAcfDebug', 'render']
        );
    }
}
