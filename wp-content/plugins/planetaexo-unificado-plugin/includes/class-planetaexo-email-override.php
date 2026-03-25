<?php
/**
 * PlanetaExo — Override de E-mails WooCommerce
 *
 * Força o uso dos templates de e-mail com identidade PlanetaExo,
 * independente do tema ativo (via filter wc_get_template).
 *
 * @package PlanetaExo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanetaExoEmailOverride {

	/**
	 * Templates que o plugin sobrescreve.
	 */
	const OVERRIDE_TEMPLATES = [
		'emails/email-header.php',
		'emails/email-footer.php',
		'emails/email-styles.php',
	];

	public static function init() {
		add_filter( 'wc_get_template', [ __CLASS__, 'force_email_templates' ], 5, 5 );
		add_filter( 'woocommerce_email_footer_text', [ __CLASS__, 'footer_text_fallback' ], 5 );
	}

	/**
	 * Força WooCommerce a usar os templates do plugin.
	 */
	public static function force_email_templates( $template, $template_name, $args, $template_path, $default_path ) {
		if ( ! in_array( $template_name, self::OVERRIDE_TEMPLATES, true ) ) {
			return $template;
		}

		$plugin_file = dirname( __DIR__ ) . '/templates/woocommerce/' . $template_name;
		if ( file_exists( $plugin_file ) ) {
			return $plugin_file;
		}

		return $template;
	}

	/**
	 * Texto do rodapé dos e-mails.
	 */
	public static function footer_text_fallback( $text ) {
		return '<strong>' . __( 'Thanks for booking your adventure with PlanetaEXO!', 'planetaexo-unificado' ) . '</strong>' . "\n\n" . __( 'If you have any questions or need further assistance before your trip, just let us know.', 'planetaexo-unificado' );
	}
}
