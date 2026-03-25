<?php
/**
 * Traduções partilhadas com o tema (domínio planetaexo) + WPML String Translation.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $name    Chave (ex.: pxo_whatsapp).
 * @param string $default Texto de referência.
 * @param string $domain  Text domain — igual ao tema para aparecer no mesmo grupo WPML.
 */
function planetaexo_t( $name, $default, $domain = 'planetaexo' ) {
	if ( function_exists( 'pxo_translate' ) ) {
		return pxo_translate( $name, $default, $domain );
	}
	if ( function_exists( 'icl_t' ) ) {
		return icl_t( $domain, $name, $default );
	}
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return apply_filters( 'wpml_translate_single_string', $default, $domain, $name );
	}
	return __( $default, $domain );
}

/**
 * Garante registo WPML mesmo se só o plugin carregar (chaves iguais às do tema).
 */
function planetaexo_plugin_register_agent_wpml_strings() {
	if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return;
	}
	$domain = 'planetaexo';
	$pairs  = array(
		'pxo_need_help_offer' => 'Need help with your offer?',
		'pxo_contact_advisor' => 'Contact %s your travel advisor at PlanetaEXO',
		'pxo_whatsapp'        => 'WhatsApp',
		'pxo_email'           => 'Email',
		'pxo_schedule_call'   => 'Schedule a call',
	);
	foreach ( $pairs as $slug => $text ) {
		if ( function_exists( 'icl_register_string' ) ) {
			icl_register_string( $domain, $slug, $text );
		} else {
			do_action( 'wpml_register_single_string', $domain, $slug, $text );
		}
	}
}
add_action( 'init', 'planetaexo_plugin_register_agent_wpml_strings', 25 );
