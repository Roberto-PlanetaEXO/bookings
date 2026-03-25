<?php
if (!defined('ABSPATH')) exit;

/* ───────────────────────────────────────────────────────
 * TESTE: Logo do origin em vez do CDN (planetaexo.com)
 * Use no functions.php do tema do planetaexo.com (Travel Monster).
 * Troca static.planetaexo.com → planetaexo.com/trips para testar se a logo carrega.
 * ─────────────────────────────────────────────────────── */
// add_filter('get_custom_logo', function($html) {
//     if (empty($html)) return $html;
//     return str_replace('https://static.planetaexo.com/', 'https://planetaexo.com/trips/', $html);
// });

/* ───────────────────────────────────────────────────────
 * TESTE: Fallback da logo (404) — planetaexo-template / bookings
 * Descomente para forçar URL que retorna 404 e testar o fallback.
 * ─────────────────────────────────────────────────────── */
// add_filter( 'pxo_logo_url', function() {
//     return 'https://static.planetaexo.com/wp-content/uploads/2026/03/planetaexo_logo-1-1.webp';
// });

/* ───────────────────────────────────────────────────────
 * 0. CHECKOUT CLÁSSICO (RESERVA)
 *
 * O WooCommerce 9.6+ força Blocks em qualquer página de checkout.
 * Este snippet desativa os Blocks APENAS na página que usa o
 * template "page-checkout-classico.php", permitindo que o
 * shortcode [woocommerce_checkout] renderize o checkout clássico.
 * ─────────────────────────────────────────────────────── */
add_filter( 'woocommerce_checkout_use_blocks', function( $use_blocks ) {
    // Só desativa Blocks se a página atual usar o template reserva
    if ( is_page() && get_page_template_slug() === 'page-checkout-classico.php' ) {
        return false;
    }
    return $use_blocks;
} );

// Garante que o WooCommerce reconheça a página clássica como checkout
add_filter( 'woocommerce_is_checkout', function( $is_checkout ) {
    if ( is_page() && get_page_template_slug() === 'page-checkout-classico.php' ) {
        return true;
    }
    return $is_checkout;
} );

/**
 * Sessão early para checkout/cart — evita erro do plugin PlanetaExo ao chamar session_start() tarde.
 * O plugin precisa de $_SESSION['c_value'] para gravar _id_cartlink no pedido.
 * Inclui requisições AJAX (wc-ajax=checkout) onde is_checkout() pode ser false.
 */
add_action( 'init', function() {
    $is_checkout_ajax = isset( $_GET['wc-ajax'] ) && $_GET['wc-ajax'] === 'checkout';
    $is_checkout_page = function_exists( 'is_checkout' ) && function_exists( 'is_cart' ) && ( is_checkout() || is_cart() );
    if ( $is_checkout_ajax || $is_checkout_page ) {
        if ( ! session_id() && ! headers_sent() ) {
            @session_start();
        }
    }
}, 1 );

/**
 * Output buffering no checkout AJAX — evita que avisos/deprecations PHP corrompam o JSON da resposta.
 * Causa comum de "There was an error processing your order" quando o pagamento de fato foi aprovado.
 */
add_action( 'init', function() {
    if ( ! isset( $_GET['wc-ajax'] ) || $_GET['wc-ajax'] !== 'checkout' ) {
        return;
    }
    ob_start();
}, 0 );
add_action( 'shutdown', function() {
    if ( ! isset( $_GET['wc-ajax'] ) || $_GET['wc-ajax'] !== 'checkout' ) {
        return;
    }
    $level = ob_get_level();
    if ( $level < 1 ) {
        return;
    }
    $out = '';
    while ( ob_get_level() > 0 ) {
        $out = ob_get_clean() . $out;
    }
    $json_start = strpos( $out, '{' );
    if ( $json_start > 0 ) {
        $out = substr( $out, $json_start );
    }
    if ( $out !== '' ) {
        echo $out;
    }
}, 0 );

/**
 * CHECKOUT RECOVERY — workaround quando 500/proxy esconde sucesso.
 * Se checkout exibe erro mas o pedido foi criado (Pressable/proxy), tenta redirecionar para order-received.
 */
add_action( 'init', function() {
	if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'pxo_checkout_recovery' ) {
		if ( ! session_id() && ! headers_sent() ) {
			@session_start();
		}
	}
}, 0 );

add_action( 'woocommerce_checkout_order_processed', function( $order_id ) {
	if ( ! $order_id ) return;
	$key = is_user_logged_in()
		? 'pxo_recovery_u' . get_current_user_id()
		: 'pxo_recovery_s' . ( session_id() ?: 'x' );
	set_transient( $key, $order_id, 300 ); // 5 min
}, 9998 );

add_action( 'wp_ajax_pxo_checkout_recovery', 'pxo_checkout_recovery_handler' );
add_action( 'wp_ajax_nopriv_pxo_checkout_recovery', 'pxo_checkout_recovery_handler' );
function pxo_checkout_recovery_handler() {
	if ( ! session_id() && ! headers_sent() ) @session_start();
	$key = is_user_logged_in()
		? 'pxo_recovery_u' . get_current_user_id()
		: 'pxo_recovery_s' . ( session_id() ?: 'x' );
	$order_id = get_transient( $key );
	if ( ! $order_id ) {
		wp_send_json_success( [ 'redirect' => '' ] );
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || ! function_exists( 'wc_get_endpoint_url' ) ) {
		wp_send_json_success( [ 'redirect' => '' ] );
		return;
	}
	delete_transient( $key );
	$redirect = $order->get_checkout_order_received_url();
	pxo_force_order_confirmation_emails( $order );
	wp_send_json_success( [ 'redirect' => $redirect ] );
}

/**
 * Força envio de e-mails de confirmação (cliente + admin).
 * Usado quando checkout retorna 500 mas o pedido foi criado — e-mails podem não ter sido enviados.
 */
function pxo_force_order_confirmation_emails( $order ) {
	if ( ! $order || ! function_exists( 'WC' ) ) return false;
	if ( $order->get_meta( '_pxo_thankyou_emails_sent' ) ) return false;
	$mailer = WC()->mailer();
	$emails = $mailer->get_emails();
	if ( empty( $emails ) ) return false;
	$sent = false;
	$order_id = $order->get_id();

	// Admin: novo pedido
	$new_order = pxo_get_email_object( $emails, 'new_order' );
	if ( $new_order && method_exists( $new_order, 'is_enabled' ) && $new_order->is_enabled() ) {
		try {
			$new_order->trigger( $order_id );
			$sent = true;
		} catch ( \Throwable $e ) {}
	}

	// Cliente: conforme status
	$status = $order->get_status();
	if ( $status === 'completed' ) {
		$email_obj = pxo_get_email_object( $emails, 'customer_completed_order' );
	} elseif ( $status === 'on-hold' ) {
		$email_obj = pxo_get_email_object( $emails, 'customer_on_hold_order' );
	} else {
		$email_obj = pxo_get_email_object( $emails, 'customer_processing_order' );
	}
	if ( $email_obj && method_exists( $email_obj, 'is_enabled' ) && $email_obj->is_enabled() ) {
		try {
			$email_obj->trigger( $order_id );
			$sent = true;
		} catch ( \Throwable $e ) {}
	}

	if ( $sent ) {
		$order->update_meta_data( '_pxo_thankyou_emails_sent', current_time( 'mysql' ) );
		$order->add_order_note( __( 'E-mails de confirmação enviados automaticamente (PlanetaExo).', 'planetaexo' ) );
		$order->save();
	}
	return $sent;
}

/* woocommerce_thankyou removido — causava envio duplicado do e-mail de confirmação.
 * O WooCommerce já envia customer_processing_order quando o pedido vai para "processing".
 * O pxo_force_order_confirmation_emails continua disponível apenas no fluxo de recovery
 * (pxo_checkout_recovery_handler) quando o checkout retorna 500 mas o pedido foi criado.
 */

/* ───────────────────────────────────────────────────────
 * 1. SETUP
 * ─────────────────────────────────────────────────────── */
function pxo_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menus([
        'primary' => __('Menu Principal', 'planetaexo'),
        'footer'  => __('Menu Rodapé',    'planetaexo'),
    ]);

    load_theme_textdomain('planetaexo', get_template_directory() . '/languages');
}
add_action('after_setup_theme', 'pxo_setup');

/**
 * Tradução da proposta (ic-campaign) compatível com WPML String Translation.
 * O scan automático por vezes não regista strings em single-ic-campaign.php; icl_register_string força a entrada.
 *
 * @param string $name    Chave única (ex.: pxo_book_now).
 * @param string $default Texto no idioma de referência (deve coincidir com o registo).
 * @param string $domain  Text domain (mantém planetaexo para o filtro WPML).
 */
function pxo_translate( $name, $default, $domain = 'planetaexo' ) {
    if ( function_exists( 'icl_t' ) ) {
        return icl_t( $domain, $name, $default );
    }
    if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
        return apply_filters( 'wpml_translate_single_string', $default, $domain, $name );
    }
    return __( $default, $domain );
}

/**
 * Regista strings da proposta no WPML (admin → String Translation → domínio planetaexo).
 */
function planetaexo_wpml_register_proposta_strings() {
    if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
        return;
    }
    $domain = 'planetaexo';
    $pairs  = array(
        'pxo_proposal_expired_banner' => 'Sua proposta expirou.',
        'pxo_wa_help_text'             => 'I need help with a proposal',
        'pxo_contact_agent_link'       => 'Fale com o agente para mais informações.',
        'pxo_offer_updated'            => 'Offer Updated',
        'pxo_start_date'               => 'Start Date:',
        'pxo_quantity_label'           => 'Quantity',
        'pxo_price_per_person'         => 'Price per Person',
        'pxo_total_label'              => 'Total',
        'pxo_itinerary'                => 'Itinerary',
        'pxo_day_n'                    => 'Day %d',
        'pxo_what_included'            => 'What is included:',
        'pxo_what_not_included'        => 'What is not included:',
        'pxo_cancellation_title'       => 'Cancellation policy:',
        'pxo_coupon_placeholder'       => 'Coupon code',
        'pxo_apply_coupon'             => 'Apply Coupon',
        'pxo_booking_total'            => 'Booking Total',
        'pxo_book_now'                => 'Book Now',
        'pxo_proposal_expired_btn'     => 'Proposal Expired',
        'pxo_need_help_offer'          => 'Need help with your offer?',
        'pxo_contact_advisor'          => 'Contact %s your travel advisor at PlanetaEXO',
        'pxo_whatsapp'                 => 'WhatsApp',
        'pxo_email'                    => 'Email',
        'pxo_schedule_call'            => 'Schedule a call',
        'pxo_footer_rights'            => 'PlanetaEXO. Todos os direitos reservados.',
        'pxo_secure_payment'           => 'Secure payment',
        'pxo_payments_alt'             => 'Visa, Mastercard, Pix',
        'pxo_remove_item'             => 'Remover item',
        'pxo_edit_proposal'            => 'Editar proposta',
        'pxo_js_select_product'       => 'Please select at least one product before continuing.',
        'pxo_js_error_process'        => 'Error processing request:',
        'pxo_js_try_again'            => 'Please try again.',
        'pxo_js_coupon_empty'         => 'Please enter a coupon code.',
        'pxo_js_coupon_applied'       => 'Coupon "%s" applied!',
    );
    foreach ( $pairs as $slug => $text ) {
        if ( function_exists( 'icl_register_string' ) ) {
            icl_register_string( $domain, $slug, $text );
        } else {
            do_action( 'wpml_register_single_string', $domain, $slug, $text );
        }
    }
}
add_action( 'init', 'planetaexo_wpml_register_proposta_strings', 20 );

/* ───────────────────────────────────────────────────────
 * 2. ASSETS
 * ─────────────────────────────────────────────────────── */
function pxo_enqueue_assets() {
    $ver = wp_get_theme()->get('Version');

    // CSS global
    wp_enqueue_style('pxo-global', get_template_directory_uri() . '/assets/css/global.css', [], $ver);

    // CSS proposta (só nas páginas de ic-campaign)
    if (is_singular('ic-campaign')) {
        wp_enqueue_style('pxo-proposta', get_template_directory_uri() . '/assets/css/proposta.css', ['pxo-global'], $ver);
        wp_enqueue_script('pxo-proposta-js', get_template_directory_uri() . '/assets/js/proposta.js', [], $ver, true);
        wp_localize_script('pxo-proposta-js', 'PxoData', [
            'ajaxUrl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pxo_proposta'),
            'checkoutUrl' => wc_get_checkout_url(),
        ]);
    }

    // CSS checkout/cart (NÃO carrega no checkout clássico para evitar conflitos)
    if ( ( is_checkout() || is_cart() || is_wc_endpoint_url() ) && ! is_page_template( 'page-checkout-classico.php' ) ) {
        wp_enqueue_style('pxo-checkout', get_template_directory_uri() . '/assets/css/checkout.css', ['pxo-global'], $ver);
    }

    // CSS checkout clássico (template reserva: page-checkout-classico.php)
    if ( is_page_template( 'page-checkout-classico.php' ) || ( is_checkout() && get_page_template_slug() === 'page-checkout-classico.php' ) ) {
        wp_enqueue_style('pxo-checkout-classico', get_template_directory_uri() . '/assets/css/checkout-classico.css', ['pxo-global'], $ver);
        
        // DEBUG: Script de monitoramento em tempo real
        wp_enqueue_script('pxo-debug-checkout', get_template_directory_uri() . '/assets/js/debug-checkout.js', ['jquery'], $ver, true);
    }
    
    // CSS checkout novo (template limpo: page-checkout-novo.php)
    if ( is_page_template( 'page-checkout-novo.php' ) || ( is_checkout() && get_page_template_slug() === 'page-checkout-novo.php' ) ) {
        wp_enqueue_style('pxo-checkout-novo', get_template_directory_uri() . '/assets/css/checkout-novo.css', ['pxo-global'], $ver);
        // Fix Booking Summary 100% width — inline para máxima prioridade
        wp_add_inline_style('pxo-checkout-novo', '
            body.checkout-novo #order_review,
            body.checkout-novo #order_review .order-details-wrapper,
            body.checkout-novo #order_review .woocommerce-checkout-review-order-table,
            body.checkout-novo #order_review table.shop_table { width: 100% !important; max-width: 100% !important; }
            body.checkout-novo #order_review table.shop_table { table-layout: fixed !important; }
        ');
        
        // JS para ajustar botões de pagamento injetados dinamicamente
        wp_enqueue_script('pxo-checkout-novo-js', get_template_directory_uri() . '/assets/js/checkout-novo.js', ['jquery'], $ver, true);
    }

    // CHECKOUT RECOVERY — workaround para erro 500/proxy quando pedido foi criado
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_enqueue_script( 'pxo-checkout-recovery', get_template_directory_uri() . '/assets/js/pxo-checkout-recovery.js', ['jquery', 'jquery-blockui'], $ver, true );
        wp_localize_script( 'pxo-checkout-recovery', 'pxoCheckoutRecovery', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pxo_checkout_recovery' ),
        ] );
    }

    // JS global
    wp_enqueue_script('pxo-global', get_template_directory_uri() . '/assets/js/global.js', [], $ver, true);
}
add_action('wp_enqueue_scripts', 'pxo_enqueue_assets');

/**
 * Troca "Order received" por "Booking Confirmed" na página order-received.
 * Afeta: título da página (h1), <title> do documento.
 */
add_filter( 'woocommerce_endpoint_order-received_title', function() {
	return 'Booking Confirmed';
}, 10, 3 );

add_filter( 'document_title_parts', function( $title ) {
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		$title['title'] = 'Booking Confirmed';
	}
	return $title;
}, 20 );

add_filter( 'woocommerce_thankyou_order_received_title', function() {
	return 'Booking Confirmed';
}, 10 );

add_filter( 'woocommerce_thankyou_order_received_text', function( $text, $order = null ) {
	return 'Thank you. Your Booking has been received.';
}, 10, 2 );

add_filter( 'gettext', function( $translated, $text, $domain ) {
	if ( $domain === 'woocommerce' && $text === 'Order received' && function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		return 'Booking Confirmed';
	}
	if ( $domain === 'woocommerce' && $text === 'Thank you. Your order has been received.' ) {
		return 'Thank you. Your Booking has been received.';
	}
	if ( $domain !== 'woocommerce' ) {
		return $translated;
	}
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $translated;
	}
	if ( $text === 'Order number:' || $text === 'Order #:' ) {
		return 'Booking number:';
	}
	return $translated;
}, 20, 3 );

add_filter( 'gettext_with_context', function( $translated, $text, $context, $domain ) {
	if ( $domain !== 'woocommerce' ) {
		return $translated;
	}
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $translated;
	}
	if ( $text === 'Order number:' || $text === 'Order #:' ) {
		return 'Booking number:';
	}
	return $translated;
}, 20, 4 );

/**
 * Campo de passaporte no checkout (PHP) — assim sobrevive ao AJAX ao clicar em "Place order".
 * Só com clone em JS o fragmento some e o CPF reaparece sem o passaporte.
 */
add_filter( 'woocommerce_billing_fields', 'pxo_register_billing_passport_field', 9999 );
function pxo_register_billing_passport_field( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}
	// Prioridade 44: após país (40) e antes do CPF típico de plugins BR (~45–50).
	$fields['billing_passport'] = array(
		'label'        => __( 'Passport number', 'planetaexo' ),
		'placeholder'  => __( 'Passport number', 'planetaexo' ),
		'required'     => false,
		'class'        => array( 'form-row-wide', 'pxo-passport-field' ),
		'priority'     => 44,
		'type'         => 'text',
		'autocomplete' => 'off',
	);
	return $fields;
}

/**
 * País ≠ Brasil: passaporte em billing_passport — não aplicar validação de CPF ao documento.
 * Remove erros típicos de plugins brasileiros em billing_cpf e exige passaporte preenchido.
 */
add_action( 'woocommerce_after_checkout_validation', 'pxo_checkout_passport_vs_cpf', 999, 2 );
function pxo_checkout_passport_vs_cpf( $data, $errors ) {
	if ( ! ( $errors instanceof WP_Error ) ) {
		return;
	}
	$country = isset( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
	if ( $country === 'BR' || $country === '' ) {
		return;
	}
	$passport = isset( $_POST['billing_passport'] ) ? trim( wp_unslash( $_POST['billing_passport'] ) ) : '';
	foreach ( $errors->get_error_codes() as $code ) {
		$c = strtolower( (string) $code );
		if ( 'billing_cpf' === $code || false !== strpos( $c, 'cpf' ) ) {
			if ( method_exists( $errors, 'remove' ) ) {
				$errors->remove( $code );
			}
		}
	}
	if ( '' === $passport ) {
		$errors->add( 'billing_passport', __( 'Informe o número do passaporte.', 'planetaexo' ) );
	}
}

/**
 * Grava passaporte no pedido (HPOS-compatible via CRUD do pedido).
 */
add_action( 'woocommerce_checkout_update_order_meta', 'pxo_save_billing_passport_order_meta', 10, 1 );
function pxo_save_billing_passport_order_meta( $order_id ) {
	$order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
	if ( ! $order ) {
		return;
	}
	$country = isset( $_POST['billing_country'] ) ? wc_clean( wp_unslash( $_POST['billing_country'] ) ) : '';
	if ( 'BR' === $country ) {
		$order->delete_meta_data( '_billing_passport' );
		$order->save();
		return;
	}
	if ( isset( $_POST['billing_passport'] ) && '' !== $_POST['billing_passport'] ) {
		$order->update_meta_data( '_billing_passport', sanitize_text_field( wp_unslash( $_POST['billing_passport'] ) ) );
	} else {
		$order->delete_meta_data( '_billing_passport' );
	}
	$order->save();
}

/**
 * Formata data/hora do pedido no fuso horário do cliente (WPML).
 * Mapeia idioma → timezone para que e-mails exibam horário correto por região.
 *
 * @param WC_Order $order Pedido.
 * @param int|null $timestamp Unix timestamp. Se null, usa date_created do pedido.
 * @return string Data formatada (ex: "Friday, Mar 20 2025 at 2:30 PM").
 */
if ( ! function_exists( 'pxo_get_order_datetime_formatted' ) ) {
	function pxo_get_order_datetime_formatted( $order, $timestamp = null ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return '';
		}
		$customer_lang = $order->get_meta( 'wpml_language' ) ?: '';
		$tz_map = array(
			'pt'     => 'America/Sao_Paulo',
			'pt-br'  => 'America/Sao_Paulo',
			'pt_BR'  => 'America/Sao_Paulo',
			'en'     => 'America/New_York',
			'en_US'  => 'America/New_York',
			'es'     => 'America/Mexico_City',
			'de'     => 'Europe/Berlin',
			'fr'     => 'Europe/Paris',
		);
		$tz = isset( $tz_map[ $customer_lang ] ) ? $tz_map[ $customer_lang ] : 'America/New_York';
		if ( $timestamp === null ) {
			$date = $order->get_date_created();
			$timestamp = $date ? $date->getTimestamp() : time();
		}
		$locale_map = array(
			'pt'    => 'pt_BR',
			'pt-br' => 'pt_BR',
			'pt_BR' => 'pt_BR',
			'en'    => 'en_US',
			'en_US' => 'en_US',
			'es'    => 'es_ES',
			'de'    => 'de_DE',
			'fr'    => 'fr_FR',
		);
		$locale = isset( $locale_map[ $customer_lang ] ) ? $locale_map[ $customer_lang ] : 'en_US';
		$prev = function_exists( 'switch_to_locale' ) ? get_locale() : null;
		if ( $prev && function_exists( 'switch_to_locale' ) ) {
			switch_to_locale( $locale );
		}
		$tz_obj = new DateTimeZone( $tz );
		$formatted = function_exists( 'wp_date' ) ? wp_date( 'l, M j Y \a\t g:i A', $timestamp, $tz_obj ) : gmdate( 'l, M j Y \a\t g:i A', $timestamp + $tz_obj->getOffset( new DateTime( '@' . $timestamp ) ) );
		if ( $prev && function_exists( 'restore_previous_locale' ) ) {
			restore_previous_locale();
		}
		return $formatted ?: date_i18n( 'l, M j Y \a\t g:i A', $timestamp );
	}
}

/* ───────────────────────────────────────────────────────
 * 2a. E-MAILS — texto do rodapé PlanetaExo
 * ─────────────────────────────────────────────────────── */
add_filter( 'woocommerce_email_footer_text', function( $text ) {
	return '<strong>' . __( 'Thanks for booking your adventure with PlanetaEXO!', 'planetaexo' ) . '</strong>' . "\n\n" . __( 'If you have any questions or need further assistance before your trip, just let us know.', 'planetaexo' );
} );

/**
 * Sanitiza HTML do itinerário para e-mail: limita imagens e remove tags que quebram layout.
 * E-mails usam tabelas; div, table, figure, etc. dentro do conteúdo podem quebrar a estrutura.
 */
if ( ! function_exists( 'pxo_email_sanitize_itinerary_html' ) ) {
	function pxo_email_sanitize_itinerary_html( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}
		// Tags permitidas: evita div, table, figure, section que quebram layout em e-mails.
		$allowed = array(
			'p'      => array( 'style' => array(), 'class' => array() ),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'ul'     => array( 'style' => array(), 'class' => array() ),
			'ol'     => array( 'style' => array(), 'class' => array() ),
			'li'     => array( 'style' => array(), 'class' => array() ),
			'h2'     => array( 'style' => array(), 'class' => array() ),
			'h3'     => array( 'style' => array(), 'class' => array() ),
			'h4'     => array( 'style' => array(), 'class' => array() ),
			'span'   => array( 'style' => array(), 'class' => array() ),
			'a'      => array( 'href' => array(), 'style' => array(), 'class' => array() ),
			'img'    => array( 'src' => array(), 'alt' => array(), 'style' => array(), 'class' => array() ),
		);
		$html = wp_kses( $html, $allowed );

		// Envolve cada img em tabela responsiva, altura uniforme (como na proposta).
		// width="100%" e max-width para mobile; margin sem negativo para não quebrar em telas pequenas.
		$img_style = 'width: 100% !important; max-width: 100% !important; height: 220px !important; object-fit: cover !important; object-position: center; display: block !important; margin: 0; border-radius: 0; vertical-align: top;';
		$table_style = 'margin: 16px 0; width: 100%; max-width: 560px;';
		$html = preg_replace_callback( '/<img([^>]*)>/i', function ( $m ) use ( $img_style, $table_style ) {
			$attrs = $m[1];
			$attrs = preg_replace( '/\s+(width|height)=["\'][^"\']*["\']/i', '', $attrs );
			if ( strpos( $attrs, 'style=' ) !== false ) {
				$attrs = preg_replace( '/style=["\']([^"\']*)["\']/', 'style="$1; ' . $img_style . '"', $attrs );
			} else {
				$attrs .= ' style="' . esc_attr( $img_style ) . '"';
			}
			return '<table width="100%" cellpadding="0" cellspacing="0" border="0" class="pxo-email-img-wrap" style="' . esc_attr( $table_style ) . '"><tr><td style="line-height: 0; font-size: 0;"><img' . $attrs . '></td></tr></table>';
		}, $html );

		return $html;
	}
}

/**
 * Helpers para e-mail "Pedido em processamento" — tabela de itens customizada.
 * Retorna mapeamento product_id (ou variation_id) → acf_slot para descricao_produto_N e data_da_viagem_N.
 * Usa pxo_product_acf_mapping quando disponível (igual single-ic-campaign).
 */
if ( ! function_exists( 'pxo_processing_get_products_indexed_by_id' ) ) {
	function pxo_processing_get_products_indexed_by_id( $post_id ) {
		$products_meta = get_post_meta( $post_id, 'products', true );
		$result       = array();
		if ( ! is_array( $products_meta ) ) {
			return $result;
		}
		$products_raw = $products_meta;
		$keys         = array_keys( $products_raw );
		$already_normalized = ! empty( $keys ) && ( $keys[0] === '1' || $keys[0] === 1 );
		if ( ! $already_normalized ) {
			$products_raw = array_values( $products_raw );
		}
		$acf_mapping_raw = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );
		$acf_mapping    = is_array( $acf_mapping_raw ) ? $acf_mapping_raw : array();
		$index = 1;
		foreach ( $products_raw as $key => $product_data ) {
			if ( ! is_array( $product_data ) || ! isset( $product_data['product_id'] ) ) {
				continue;
			}
			$n         = $already_normalized ? (int) $key : (int) $key + 1;
			$acf_slot  = isset( $acf_mapping[ (string) $n ] ) ? (int) $acf_mapping[ (string) $n ] : $n;
			$pid       = (int) $product_data['product_id'];
			$vid       = isset( $product_data['variation_id'] ) ? (int) $product_data['variation_id'] : 0;
			$result[ $pid ] = $acf_slot;
			if ( $vid > 0 ) {
				$result[ $vid ] = $acf_slot;
			}
			$index++;
		}
		return $result;
	}
}

if ( ! function_exists( 'pxo_processing_custom_email_order_items_table' ) ) {
	function pxo_processing_custom_email_order_items_table( $order, $args = array() ) {
		$defaults = array(
			'show_sku'      => false,
			'show_image'    => false,
			'image_size'    => array( 32, 32 ),
			'plain_text'    => false,
			'sent_to_admin' => false,
		);
		$args  = wp_parse_args( $args, $defaults );
		$items = $order->get_items();
		if ( ! is_array( $items ) ) {
			return '';
		}
		$_id_cartlink           = $order->get_meta( '_id_cartlink' );
		$indice_textos_produtos = pxo_processing_get_products_indexed_by_id( $_id_cartlink );
		ob_start();
		foreach ( $items as $item_id => $item ) :
			$product      = $item->get_product();
			$purchase_note = $product ? $product->get_purchase_note() : '';
			$product_id   = $item->get_product_id();
			$variation_id  = $item->get_variation_id();
			$real_product_id = $variation_id > 0 ? $variation_id : $product_id;
			$default_lang = function_exists( 'wpml_get_default_language' ) ? wpml_get_default_language() : null;
			$real_product_id = apply_filters( 'wpml_object_id', $real_product_id, 'product', true, $default_lang );
			$cont_viagens   = isset( $indice_textos_produtos[ $real_product_id ] ) ? $indice_textos_produtos[ $real_product_id ] : ( isset( $indice_textos_produtos[ $product_id ] ) ? $indice_textos_produtos[ $product_id ] : 1 );
			?>
		<tr>
			<td style="text-align:left; border: 0;">
				<strong style="font-family: Helvetica; font-weight: 700; font-size: 25px; line-height: 25px; letter-spacing: 0px; padding: 30px 48px 0px 48px; display: block;"><?php echo esc_html( $item->get_name() ); ?></strong>
				<?php
				if ( ! empty( $_id_cartlink ) && function_exists( 'pxo_field' ) && function_exists( 'pxo_format_date' ) ) {
					$itinerario    = pxo_field( 'descricao_produto_' . $cont_viagens, $_id_cartlink );
					$data_da_viagem = pxo_field( 'data_da_viagem_' . $cont_viagens, $_id_cartlink );
					if ( $data_da_viagem != '' ) {
						$data_formatada = pxo_format_date( $data_da_viagem );
						echo '<p style="margin-top: 1em; font-family: Helvetica; font-weight: 400; font-size: 18px; line-height: 20px; letter-spacing: 0px; text-decoration: underline; text-decoration-style: solid; text-decoration-offset: 0%; text-decoration-thickness: 0%; padding: 0 48px 0px 48px; display: block;">';
						esc_attr_e( 'Start Date', 'woocommerce' );
						echo ': ' . esc_html( $data_formatada ) . '</p>';
					}
					if ( $itinerario ) {
						$itinerario = function_exists( 'pxo_email_sanitize_itinerary_html' ) ? pxo_email_sanitize_itinerary_html( $itinerario ) : $itinerario;
						echo '<div class="email-itinerary" style="font-family: Helvetica, Arial, sans-serif; font-weight: normal; font-size: 16px; line-height: 25px; letter-spacing: 0; color: #3c3c3c; padding: 15px 48px 60px 48px; display: block;">' . wp_kses_post( $itinerario ) . '</div>';
					}
				}
				?>
			<table cellspacing="0" cellpadding="6" border="1" width="100%" style="border: 0px;">
				<tr style="border-bottom: 1px solid #eaebed4f;border-top: 1px solid #eaebed4f;">
					<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php echo esc_html__( 'Quantity', 'woocommerce' ); ?></td>
					<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo esc_html( $item->get_quantity() ); ?></td>
				</tr>
				<tr style="border-bottom: 1px solid #eaebed4f;border-top: 1px solid #eaebed4f;">
					<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php echo esc_html__( 'Price per person', 'woocommerce' ); ?></td>
					<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wc_price( $order->get_item_total( $item, false, true ) ); ?></td>
				</tr>
				<tr style="border-bottom: 1px solid #eaebed4f;border-top: 1px solid #eaebed4f;">
					<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php echo esc_html__( 'Total Price', 'woocommerce' ); ?></td>
					<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wc_price( $order->get_item_total( $item, false, true ) * $item->get_quantity() ); ?></td>
				</tr>
			</table>
			</td>
		</tr>
			<?php
			if ( $purchase_note ) :
				?>
		<tr>
			<td colspan="3" style="text-align:left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?></td>
		</tr>
				<?php
			endif;
		endforeach;
		return ob_get_clean();
	}
}

/**
 * Tabela de itens para e-mail "Novo pedido" (admin) — inclui ACF (descricao_produto, data_da_viagem).
 * Mesmo layout dos e-mails customer-processing-order e admin-failed-order.
 */
if ( ! function_exists( 'pxo_admin_new_order_custom_email_order_items_table' ) ) {
	function pxo_admin_new_order_custom_email_order_items_table( $order, $args = array() ) {
		$args  = wp_parse_args( $args, array( 'show_sku' => false, 'show_image' => false, 'image_size' => array( 32, 32 ), 'plain_text' => false, 'sent_to_admin' => false ) );
		$items = $order->get_items();
		if ( ! is_array( $items ) ) {
			return '';
		}
		$_id_cartlink           = $order->get_meta( '_id_cartlink' );
		$indice_textos_produtos = pxo_processing_get_products_indexed_by_id( $_id_cartlink );
		ob_start();
		foreach ( $items as $item_id => $item ) :
			$product       = $item->get_product();
			$purchase_note = $product ? $product->get_purchase_note() : '';
			$product_id    = $item->get_product_id();
			$variation_id  = $item->get_variation_id();
			$real_id       = $variation_id > 0 ? $variation_id : $product_id;
			$default_lang  = function_exists( 'wpml_get_default_language' ) ? wpml_get_default_language() : null;
			$real_id       = apply_filters( 'wpml_object_id', $real_id, 'product', true, $default_lang );
			$cont          = isset( $indice_textos_produtos[ $real_id ] ) ? $indice_textos_produtos[ $real_id ] : ( isset( $indice_textos_produtos[ $product_id ] ) ? $indice_textos_produtos[ $product_id ] : 1 );
			?>
		<tr>
			<td style="text-align:left; border: 0;">
				<strong style="font-family: Helvetica; font-weight: 700; font-size: 25px; line-height: 25px; letter-spacing: 0px; padding: 30px 48px 0px 48px; display: block;"><?php echo esc_html( $item->get_name() ); ?></strong>
				<?php
				if ( ! empty( $_id_cartlink ) && function_exists( 'pxo_field' ) && function_exists( 'pxo_format_date' ) ) {
					$itinerario  = pxo_field( 'descricao_produto_' . $cont, $_id_cartlink );
					$data_viagem = pxo_field( 'data_da_viagem_' . $cont, $_id_cartlink );
					if ( $data_viagem != '' ) {
						$data_formatada = pxo_format_date( $data_viagem );
						echo '<p style="margin-top: 1em; font-family: Helvetica; font-weight: 400; font-size: 18px; line-height: 20px; letter-spacing: 0px; text-decoration: underline; padding: 0 48px 0px 48px; display: block;">';
						esc_attr_e( 'Start Date', 'woocommerce' );
						echo ': ' . esc_html( $data_formatada ) . '</p>';
					}
					if ( $itinerario ) {
						$itinerario = function_exists( 'pxo_email_sanitize_itinerary_html' ) ? pxo_email_sanitize_itinerary_html( $itinerario ) : $itinerario;
						echo '<div class="email-itinerary" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; line-height: 25px; letter-spacing: 0; color: #3c3c3c; padding: 15px 48px 60px 48px; display: block;">' . wp_kses_post( $itinerario ) . '</div>';
					}
				}
				?>
				<table cellspacing="0" cellpadding="6" border="1" width="100%" style="border: 0;">
					<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
						<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0; font-size: 13px; font-weight: 600; color: #555;"><?php echo esc_html__( 'Quantity', 'woocommerce' ); ?></td>
						<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo esc_html( $item->get_quantity() ); ?></td>
					</tr>
					<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
						<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0; font-size: 13px; font-weight: 600; color: #555;"><?php echo esc_html__( 'Price per person', 'woocommerce' ); ?></td>
						<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wc_price( $order->get_item_total( $item, false, true ) ); ?></td>
					</tr>
					<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
						<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0; font-size: 13px; font-weight: 600; color: #555;"><?php echo esc_html__( 'Total Price', 'woocommerce' ); ?></td>
						<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wc_price( $order->get_item_total( $item, false, true ) * $item->get_quantity() ); ?></td>
					</tr>
				</table>
			</td>
		</tr>
			<?php
			if ( $purchase_note ) :
				?>
		<tr>
			<td colspan="3" style="text-align:left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?></td>
		</tr>
				<?php
			endif;
		endforeach;
		return ob_get_clean();
	}
}

/* ───────────────────────────────────────────────────────
 * 2a2. ORDER ACTIONS — disparo de e-mails para teste (REMOVER APÓS TESTES)
 *
 * Adiciona opções no dropdown "Order actions" da tela de edição de pedido.
 * Escolha a ação, clique em Update e o e-mail será enviado.
 * ─────────────────────────────────────────────────────── */
add_filter( 'woocommerce_order_actions', function( $actions ) {
	$actions['pxo_test_new_order']             = __( '[TESTE] Enviar e-mail: Novo pedido (admin)', 'planetaexo' );
	$actions['pxo_test_cancelled_order']      = __( '[TESTE] Enviar e-mail: Pedido cancelado', 'planetaexo' );
	$actions['pxo_test_failed_order']         = __( '[TESTE] Enviar e-mail: Pedido falhou (admin)', 'planetaexo' );
	$actions['pxo_test_processing_order']    = __( '[TESTE] Enviar e-mail: Pedido em processamento', 'planetaexo' );
	$actions['pxo_test_completed_order']     = __( '[TESTE] Enviar e-mail: Pedido concluído', 'planetaexo' );
	$actions['pxo_test_invoice']              = __( '[TESTE] Enviar e-mail: Fatura do cliente', 'planetaexo' );
	$actions['pxo_test_pix_paid']             = __( '[TESTE] Enviar e-mail: PIX pago (admin)', 'planetaexo' );
	$actions['pxo_test_pix_close_to_expires']  = __( '[TESTE] Enviar e-mail: PIX perto de expirar (cliente)', 'planetaexo' );
	return $actions;
} );

/**
 * Mapa id → classe WC. Diferentes versões do WooCommerce usam id ou classe como chave.
 */
function pxo_get_email_object( $emails, string $key ) {
	if ( isset( $emails[ $key ] ) ) {
		return $emails[ $key ];
	}
	$class_map = [
		'cancelled_order'             => 'cancelled_order',
		'failed_order'                => 'failed_order',
		'new_order'                   => 'new_order',
		'customer_processing_order'   => 'customer_processing_order',
		'customer_completed_order'    => 'customer_completed_order',
		'customer_on_hold_order'      => 'customer_on_hold_order',
		'customer_invoice'            => 'customer_invoice',
	];
	$alt = $class_map[ $key ] ?? $key;
	if ( isset( $emails[ $alt ] ) ) {
		return $emails[ $alt ];
	}
	foreach ( $emails as $e ) {
		if ( is_object( $e ) && isset( $e->id ) && $e->id === $key ) {
			return $e;
		}
	}
	return null;
}

/**
 * Helper: dispara e-mail de teste e exibe aviso no admin em caso de falha.
 */
function pxo_trigger_test_email( $order, string $key, string $label, callable $trigger_fn = null ): bool {
	$mailer  = WC()->mailer();
	$emails  = $mailer->get_emails();
	$email_obj = pxo_get_email_object( $emails, $key );
	if ( ! $email_obj ) {
		$msg = sprintf(
			/* translators: %1$s = email label, %2$s = email key */
			__( '[TESTE] E-mail não encontrado: %1$s (id: %2$s). Verifique WooCommerce → Configurações → E-mails.', 'planetaexo' ),
			$label,
			$key
		);
		$order->add_order_note( $msg );
		set_transient( 'pxo_email_test_failed', $msg, 45 );
		return false;
	}
	try {
		if ( $trigger_fn ) {
			$trigger_fn( $email_obj, $order );
		} else {
			$email_obj->trigger( $order->get_id() );
		}
		$order->add_order_note( sprintf( __( '[TESTE] E-mail enviado: %s', 'planetaexo' ), $label ) );
		return true;
	} catch ( \Throwable $e ) {
		$msg = sprintf(
			/* translators: %1$s = email label, %2$s = error message */
			__( '[TESTE] Erro ao enviar e-mail "%1$s": %2$s', 'planetaexo' ),
			$label,
			$e->getMessage()
		);
		$order->add_order_note( $msg );
		set_transient( 'pxo_email_test_failed', $msg, 45 );
		return false;
	}
}

add_action( 'admin_notices', function() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) return;
	$msg = get_transient( 'pxo_email_test_failed' );
	if ( ! $msg ) return;
	delete_transient( 'pxo_email_test_failed' );
	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'PlanetaExo — Teste de e-mail', 'planetaexo' ) . '</strong><br>' . esc_html( $msg ) . '</p></div>';
} );

// Usa id do e-mail; pxo_get_email_object tenta id, classe e busca por $email->id
add_action( 'woocommerce_order_action_pxo_test_new_order', function( $order ) {
	pxo_trigger_test_email( $order, 'new_order', __( 'Novo pedido (admin)', 'planetaexo' ) );
} );

add_action( 'woocommerce_order_action_pxo_test_cancelled_order', function( $order ) {
	pxo_trigger_test_email( $order, 'cancelled_order', __( 'Pedido cancelado', 'planetaexo' ) );
} );

add_action( 'woocommerce_order_action_pxo_test_failed_order', function( $order ) {
	pxo_trigger_test_email( $order, 'failed_order', __( 'Pedido falhou (admin)', 'planetaexo' ) );
} );

add_action( 'woocommerce_order_action_pxo_test_processing_order', function( $order ) {
	pxo_trigger_test_email( $order, 'customer_processing_order', __( 'Pedido em processamento', 'planetaexo' ) );
} );

add_action( 'woocommerce_order_action_pxo_test_completed_order', function( $order ) {
	pxo_trigger_test_email( $order, 'customer_completed_order', __( 'Pedido concluído', 'planetaexo' ) );
} );

add_action( 'woocommerce_order_action_pxo_test_invoice', function( $order ) {
	pxo_trigger_test_email( $order, 'customer_invoice', __( 'Fatura do cliente', 'planetaexo' ), function( $email, $order ) {
		$email->trigger( $order->get_id(), $order, true );
	} );
} );

add_action( 'woocommerce_order_action_pxo_test_pix_paid', function( $order ) {
	$pix = null;
	if ( class_exists( 'Piggly\WooPixGateway\CoreConnector' ) && class_exists( 'Piggly\WooPixGateway\Core\Repo\PixRepo' ) ) {
		$repo   = new \Piggly\WooPixGateway\Core\Repo\PixRepo( \Piggly\WooPixGateway\CoreConnector::plugin() );
		$txid   = $order->get_meta( '_pgly_wc_piggly_pix_latest_pix' );
		if ( $txid ) {
			$pix = $repo->byId( $txid, false );
			if ( $pix ) {
				$pix->setOrder( $order );
			}
		}
		if ( ! $pix ) {
			$pixs = $repo->byOrder( $order );
			$pix  = ! empty( $pixs ) ? $pixs[0] : null;
		}
	}
	if ( ! $pix ) {
		$msg = __( '[TESTE] Este pedido não possui PIX associado. Use um pedido pago ou com PIX gerado (plugin Pix por Piggly).', 'planetaexo' );
		$order->add_order_note( $msg );
		set_transient( 'pxo_email_test_failed', $msg, 45 );
		return;
	}
	pxo_trigger_test_email( $order, 'wc_piggly_pix_admin_confirmed', __( 'PIX pago (admin)', 'planetaexo' ), function( $email, $order ) use ( $pix ) {
		$email->trigger( $pix, 'waiting', \Piggly\WooPixGateway\Core\Entities\PixEntity::STATUS_PAID );
	} );
} );

add_action( 'woocommerce_order_action_pxo_test_pix_close_to_expires', function( $order ) {
	$pix = null;
	if ( class_exists( 'Piggly\WooPixGateway\CoreConnector' ) && class_exists( 'Piggly\WooPixGateway\Core\Repo\PixRepo' ) ) {
		$repo = new \Piggly\WooPixGateway\Core\Repo\PixRepo( \Piggly\WooPixGateway\CoreConnector::plugin() );
		$txid = $order->get_meta( '_pgly_wc_piggly_pix_latest_pix' );
		if ( $txid ) {
			$pix = $repo->byId( $txid, false );
			if ( $pix ) {
				$pix->setOrder( $order );
			}
		}
		if ( ! $pix ) {
			$pixs = $repo->byOrder( $order );
			$pix  = ! empty( $pixs ) ? $pixs[0] : null;
		}
	}
	if ( ! $pix ) {
		$msg = __( '[TESTE] Este pedido não possui PIX associado. Use um pedido com PIX gerado (plugin Pix por Piggly).', 'planetaexo' );
		$order->add_order_note( $msg );
		set_transient( 'pxo_email_test_failed', $msg, 45 );
		return;
	}
	pxo_trigger_test_email( $order, 'wc_piggly_pix_pix_close_to_expires', __( 'PIX perto de expirar (cliente)', 'planetaexo' ), function( $email, $order ) use ( $pix ) {
		$email->trigger( $pix );
	} );
} );

/* ───────────────────────────────────────────────────────
 * 2b. CHECKOUT BLOCKS — override inline via wp_add_inline_style
 *
 * Injeta o checkout.css como inline após o handle do WC Blocks
 * (wc-blocks-style-checkout). Todos os !important do nosso CSS
 * sobrepõem os estilos inline que o WC Blocks injeta depois.
 * ─────────────────────────────────────────────────────── */
add_action('wp_enqueue_scripts', function() {
    if ( ! ( function_exists('is_checkout') && is_checkout() ) ) return;

    $css_file = get_template_directory() . '/assets/css/checkout.css';
    if ( ! file_exists($css_file) ) return;
    $css = file_get_contents($css_file);

    // Handle preferencial: checkout-específico do WC Blocks
    foreach ( ['wc-blocks-style-checkout', 'wc-blocks-style'] as $handle ) {
        if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) ) {
            wp_add_inline_style( $handle, $css );
            return;
        }
    }

    // Fallback: cria handle virtual e injeta no <head>
    wp_register_style( 'pxo-checkout-override', false, ['wc-blocks-style'], null );
    wp_enqueue_style( 'pxo-checkout-override' );
    wp_add_inline_style( 'pxo-checkout-override', $css );
}, 200); // prio 200 → após WC Blocks registrar os handles (prio 5–10)

/* Fallback extra: wp_footer prio 99 — garante que o CSS está presente
 * mesmo se o handle wc-blocks-style-checkout ainda não estiver registrado
 * no momento de wp_enqueue_scripts (ex: themes sem WC Blocks enqueued). */
add_action('wp_footer', function() {
    if ( ! ( function_exists('is_checkout') && is_checkout() ) ) return;
    // Só injeta se wp_add_inline_style NÃO tiver encontrado o handle
    if ( wp_style_is( 'wc-blocks-style-checkout', 'done' )
      || wp_style_is( 'wc-blocks-style', 'done' )
      || wp_style_is( 'pxo-checkout-override', 'done' ) ) return;

    $css_file = get_template_directory() . '/assets/css/checkout.css';
    if ( ! file_exists($css_file) ) return;
    echo '<style id="pxo-checkout-override">' . file_get_contents($css_file) . '</style>';
}, 999);

/* ───────────────────────────────────────────────────────
 * 3. WIDGETS / SIDEBARS (opcional)
 * ─────────────────────────────────────────────────────── */
function pxo_widgets_init() {
    register_sidebar([
        'name'          => __('Sidebar Principal', 'planetaexo'),
        'id'            => 'sidebar-1',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'pxo_widgets_init');

/* ───────────────────────────────────────────────────────
 * 4. WooCommerce — estilos padrão (mantém no checkout/cart)
 * ─────────────────────────────────────────────────────── */
add_filter('woocommerce_enqueue_styles', function( $styles ) {
    // Mantém CSS base do WC nas páginas de checkout, carrinho e conta
    if ( is_checkout() || is_cart() || is_account_page() || is_wc_endpoint_url() ) {
        unset( $styles['woocommerce-smallscreen'] ); // removemos o mobile WC (substituído pelo nosso)
        return $styles;
    }
    return []; // sem WC styles em páginas normais do site
});

/* ───────────────────────────────────────────────────────
 * 4b/4c. Lógica de carrinho movida para o plugin pxo-cart-fix.
 *
 * O plugin intercepta ?c= no hook 'wp' (antes do template_redirect)
 * e intercepta ?book=1 antes do Cart Link (template_redirect prio 1).
 * ─────────────────────────────────────────────────────── */

/* ───────────────────────────────────────────────────────
 * 5. HELPERS
 * ─────────────────────────────────────────────────────── */

/**
 * Retorna URL do logo
 */
function pxo_logo_url(): string {
    $override = apply_filters( 'pxo_logo_url', null );
    if ( $override ) return $override;

    // 1. Logo configurada no Customizer (Aparência > Personalizar > Identidade do Site)
    $logo_id = get_theme_mod('custom_logo');
    if ($logo_id) {
        $url = wp_get_attachment_image_url($logo_id, 'full');
        if ($url) return $url;
    }

    // 2. Tenta extrair a URL do HTML retornado por get_custom_logo() (compatível com qualquer tema)
    if (function_exists('get_custom_logo')) {
        $logo_html = get_custom_logo();
        if ($logo_html && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $logo_html, $m)) {
            return $m[1];
        }
    }

    // 3. Arquivo local no tema
    $local = get_template_directory() . '/assets/img/logo.svg';
    if (file_exists($local)) {
        return get_template_directory_uri() . '/assets/img/logo.svg';
    }
    $local_png = get_template_directory() . '/assets/img/logo.png';
    if (file_exists($local_png)) {
        return get_template_directory_uri() . '/assets/img/logo.png';
    }

    // 4. Fallback: logo branca hospedada no servidor
    return 'https://bookings.planetaexo.com/wp-content/uploads/2026/03/exo_logo_white.png';
}

/**
 * URL de fallback quando a logo principal retorna 404 (ex.: CDN com arquivo ausente).
 * Usada no atributo data-fallback das imagens de logo.
 */
function pxo_logo_fallback_url(): string {
	return apply_filters( 'pxo_logo_fallback_url', 'https://bookings.planetaexo.com/wp-content/uploads/2026/03/exo_logo_white.png' );
}

/**
 * Script inline: quando a logo falha ao carregar (404), troca para a URL de fallback.
 */
add_action( 'wp_footer', function() {
	$fallback = pxo_logo_fallback_url();
	?>
	<script>
	(function() {
		function useFallback(img) {
			var fb = img.getAttribute('data-pxo-logo-fallback');
			if (fb) { img.src = fb; img.removeAttribute('data-pxo-logo-fallback'); }
		}
		document.querySelectorAll('img[data-pxo-logo-fallback]').forEach(function(img) {
			img.addEventListener('error', function() { useFallback(img); });
			if (img.complete && img.naturalWidth === 0) useFallback(img);
		});
	})();
	</script>
	<?php
}, 99 );

/**
 * Formata valor em BRL
 */
function pxo_format_brl( $value ): string {
    return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
}

/**
 * Retorna dados do agente vinculado à proposta.
 *
 * Prioridade de fonte:
 *   1. Campo ACF 'agente_responsavel' no post (user picker) — selecionado na edição da proposta
 *   2. Fallback: post_author
 *
 * Dados lidos do perfil do usuário (user meta ACF):
 *   – agent_photo          → imagem
 *   – agent_whatsapp       → número WhatsApp
 *   – link_agendamento     → URL Google Calendar etc.
 *
 * A foto é sempre resolvida para URL string antes de retornar.
 */
function pxo_get_agent( $post_id ): array {
    $empty = [
        'name'          => '',
        'photo'         => '',
        'whatsapp'      => '',
        'email'         => '',
        'phone'         => '',
        'schedule_link' => '',
    ];

    // ── 1. Tenta o campo 'agente_responsavel' definido na proposta ────────
    $agent_user_id = 0;
    if ( function_exists( 'get_field' ) ) {
        $agent_field = get_field( 'agente_responsavel', $post_id );
        // ACF user field pode retornar array (objeto do usuário) ou ID
        if ( is_array( $agent_field ) && ! empty( $agent_field['ID'] ) ) {
            $agent_user_id = (int) $agent_field['ID'];
        } elseif ( is_numeric( $agent_field ) && (int) $agent_field > 0 ) {
            $agent_user_id = (int) $agent_field;
        }
    }
    // ── 2. Fallback: post_author ──────────────────────────────────────────
    if ( ! $agent_user_id ) {
        $agent_user_id = (int) get_post_field( 'post_author', $post_id );
    }
    if ( ! $agent_user_id ) return $empty;

    $author_id = $agent_user_id;
    $user      = get_userdata( $author_id );
    if ( ! $user ) return $empty;

    $uid = 'user_' . $author_id;

    // ── Foto: tenta get_field() e get_user_meta() com vários meta_keys ───
    $photo_raw = null;
    if ( function_exists( 'get_field' ) ) {
        $photo_raw = get_field( 'agent_photo', $uid );
        if ( ! $photo_raw ) $photo_raw = get_field( 'foto', $uid );
    }
    if ( ! $photo_raw ) {
        $photo_raw = get_user_meta( $author_id, 'agent_photo', true )
                  ?: get_user_meta( $author_id, 'foto',        true );
    }
    // Resolve para URL: ACF pode retornar array, ID (int/string) ou URL direta
    $photo_url = '';
    if ( is_array( $photo_raw ) ) {
        $photo_url = $photo_raw['url'] ?? '';
    } elseif ( is_numeric( $photo_raw ) && (int) $photo_raw > 0 ) {
        $photo_url = wp_get_attachment_url( (int) $photo_raw ) ?: '';
    } elseif ( is_string( $photo_raw ) && $photo_raw ) {
        $photo_url = $photo_raw; // já é uma URL
    }

    // ── WhatsApp ──────────────────────────────────────────────────────────
    $whatsapp = '';
    if ( function_exists( 'get_field' ) ) {
        $whatsapp = get_field( 'agent_whatsapp',    $uid )
                 ?: get_field( 'telefone_whatsapp', $uid )
                 ?: '';
    }
    if ( ! $whatsapp ) {
        $whatsapp = get_user_meta( $author_id, 'agent_whatsapp',    true )
                 ?: get_user_meta( $author_id, 'telefone_whatsapp', true )
                 ?: '';
    }

    // ── Link para agendamento ─────────────────────────────────────────────
    $schedule_link = '';
    if ( function_exists( 'get_field' ) ) {
        $schedule_link = get_field( 'link_agendamento',    $uid )
                      ?: get_field( 'agent_schedule_link', $uid )
                      ?: '';
    }
    if ( ! $schedule_link ) {
        $schedule_link = get_user_meta( $author_id, 'link_agendamento',    true )
                      ?: get_user_meta( $author_id, 'agent_schedule_link', true )
                      ?: '';
    }

    return [
        'name'          => $user->display_name ?: '',
        'photo'         => $photo_url,
        'whatsapp'      => (string) $whatsapp      ?: '',
        'email'         => $user->user_email       ?: '',
        'phone'         => get_user_meta( $author_id, 'agent_phone', true ) ?: '',
        'schedule_link' => (string) $schedule_link ?: '',
    ];
}

/* ───────────────────────────────────────────────────────
 * 6. HELPERS de campo e formatação de data
 * ─────────────────────────────────────────────────────── */

/**
 * Formata data que pode vir em Ymd, d/m/Y ou Y-m-d.
 * Formato de saída conforme região WPML: pt/pt-br → d/m/Y, en/outros → F j, Y (ex: March 20, 2025).
 */
if ( ! function_exists( 'pxo_format_date' ) ) {
	function pxo_format_date( $raw ): string {
		$raw = (string) $raw;
		if ( ! $raw ) return '';
		$dt = DateTime::createFromFormat( 'Ymd', $raw )
			?: DateTime::createFromFormat( 'd/m/Y', $raw )
			?: DateTime::createFromFormat( 'Y-m-d', $raw );
		if ( ! $dt ) return esc_html( $raw );
		$lang = '';
		if ( defined( 'ICL_SITEPRESS_VERSION' ) && function_exists( 'apply_filters' ) ) {
			$lang = apply_filters( 'wpml_current_language', '' );
		}
		$format = ( $lang === 'pt' || $lang === 'pt-br' || $lang === 'pt_BR' ) ? 'd/m/Y' : 'F j, Y';
		return $dt->format( $format );
	}
}

/* ───────────────────────────────────────────────────────
 * 7. HELPER: lê campo ACF com fallback para get_post_meta()
 *
 * Quando o campo não tem field_key registrado (situação que ocorre com
 * campos locais cujo post_excerpt está vazio no DB), get_field() retorna
 * null. Este helper garante que o valor seja lido diretamente pelo meta_key.
 * ─────────────────────────────────────────────────────── */
if ( ! function_exists( 'pxo_field' ) ) :
function pxo_field( $field_name, $post_id, $format_value = true ) {
    $value = null;

    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field_name, $post_id, $format_value );
    }

    // Fallback: lê diretamente do post_meta (retorna a string bruta)
    if ( $value === null || $value === false || $value === '' ) {
        $value = get_post_meta( $post_id, $field_name, true );
    }

    return $value;
}
endif;

/* ───────────────────────────────────────────────────────
 * 8. INTERCEPT ic-campaign (prioridade 1, antes do Cart Link plugin na 5)
 *
 * Fluxo:
 *   URL /slug-da-proposta/         → renderiza single-ic-campaign.php
 *   URL /slug-da-proposta/?book=1  → Cart Link adiciona ao carrinho e redireciona para /cart/
 * ─────────────────────────────────────────────────────── */
/**
 * Força template page-checkout-novo.php em TODAS as páginas de checkout (incl. checkout-trips WPML).
 * Quando a tradução EN não tem o template atribuído, get_page_template_slug() retorna vazio/default.
 */
add_filter( 'template_include', function( $template ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $template;
	}
	$slug = get_page_template_slug();
	// Não alterar se já usa o template clássico (reserva)
	if ( $slug === 'page-checkout-classico.php' ) {
		return $template;
	}
	// Força checkout novo para checkout padrão e checkout-trips (EN)
	$novo = get_template_directory() . '/page-checkout-novo.php';
	return file_exists( $novo ) ? $novo : $template;
}, 20 );

add_action( 'template_redirect', 'pxo_intercept_proposta', 1 );

/* ─────────────────────────────────────────────────────────
 * 10. PURGA DE CACHE ao salvar ic-campaign
 *
 * O ic-campaign é interceptado como 404, por isso o Nginx Helper e
 * o objeto de cache do WP não purgar automaticamente a URL da proposta.
 * Este hook faz a purga explicitamente sempre que o post é salvo.
 * Cobre: Nginx Helper, WP Rocket, LiteSpeed Cache e Redis Object Cache.
 * ───────────────────────────────────────────────────────── */
add_action( 'save_post_ic-campaign', 'pxo_purge_proposta_cache', 10, 1 );
add_action( 'acf/save_post',         'pxo_purge_proposta_cache_acf', 10, 1 );

function pxo_purge_proposta_cache( int $post_id ): void {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;

    // URL da proposta (o slug do post é a URL, pois rewrite=false)
    $post = get_post( $post_id );
    if ( ! $post ) return;
    $proposta_url = home_url( '/' . $post->post_name . '/' );

    // ── 1. Nginx Helper (purga via API interna) ─────────────────────
    if ( class_exists( 'Nginx_Helper' ) ) {
        global $nginx_purger;
        if ( $nginx_purger && method_exists( $nginx_purger, 'purge_url' ) ) {
            $nginx_purger->purge_url( $proposta_url );
        }
    }

    // ── 2. WP Rocket ───────────────────────────────────────
    if ( function_exists( 'rocket_clean_post' ) ) {
        rocket_clean_post( $post_id );
    }

    // ── 3. LiteSpeed Cache ────────────────────────────────
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge( $proposta_url );
    }

    // ── 4. Redis / WP Object Cache ──────────────────────────
    clean_post_cache( $post_id );   // limpa meta + post do object cache
    wp_cache_delete( $post_id, 'posts' );
    wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * Chamado pelo hook do ACF (cobre saves diretos via ACF que não disparam save_post)
 */
function pxo_purge_proposta_cache_acf( $post_id ): void {
    if ( ! is_numeric( $post_id ) ) return;
    pxo_purge_proposta_cache( (int) $post_id );
}

/* ───────────────────────────────────────────────────────
 * 9. AJAX pxo_update_quantities — movido para o plugin Planeta Exo Unificado
 *    (includes/class-planetaexo-cart-link.php → PlanetaExoCartLink::ajax_update_quantities)
 * ─────────────────────────────────────────────────────── */

/* ───────────────────────────────────────────────────────
 * 9b. REMOVIDO — pxo_process_cart_token (?pxo_load=) não é mais usado.
 * ─────────────────────────────────────────────────────── */

function pxo_intercept_proposta(): void {
    global $wp, $wp_query, $post;

    // Só atua em 404 — o ic-campaign tem rewrite=false, portanto sempre é 404
    if ( ! is_404() ) {
        return;
    }

    $request = trim( $wp->request, '/' );

    // ── Detecta prefixo de idioma do WPML (ex: "pt/slug", "en/slug") ──────
    $detected_lang = '';
    if ( function_exists( 'icl_get_languages' ) || defined( 'ICL_SITEPRESS_VERSION' ) ) {
        // Pega todos os idiomas ativos no WPML
        $active_langs = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
        if ( is_array( $active_langs ) ) {
            foreach ( $active_langs as $lang_code => $lang_data ) {
                // O prefixo na URL pode ser o código do idioma (ex: "pt", "pt-br", "en")
                $prefix = $lang_code;
                if ( strpos( $request, $prefix . '/' ) === 0 ) {
                    $detected_lang = $lang_code;
                    $request       = substr( $request, strlen( $prefix ) + 1 ); // remove "pt/"
                    break;
                }
                // Também testa o code com hífen convertido em hífen (pt-br)
                if ( isset( $lang_data['url_info']['language_code'] ) ) {
                    $url_code = $lang_data['url_info']['language_code'];
                    if ( $url_code !== $prefix && strpos( $request, $url_code . '/' ) === 0 ) {
                        $detected_lang = $lang_code;
                        $request       = substr( $request, strlen( $url_code ) + 1 );
                        break;
                    }
                }
            }
        }
    }

    // Localiza o ic-campaign pelo slug da URL (já sem prefixo de idioma)
    $campaign_post = get_page_by_path( $request, OBJECT, 'ic-campaign' );

    if ( ! $campaign_post || $campaign_post->post_status !== 'publish' ) {
        return;
    }

    // Com ?book=1 o Cart Link plugin deve processar (adiciona ao carrinho)
    if ( isset( $_GET['book'] ) && $_GET['book'] === '1' ) {
        return;
    }

    // ── Aplica o idioma detectado no WPML ────────────────────────────────
    if ( $detected_lang && defined( 'ICL_SITEPRESS_VERSION' ) ) {
        do_action( 'wpml_switch_language', $detected_lang );
    }

    // ── Configura o WP_Query para que have_posts() / the_post() funcionem ──
    $post                        = $campaign_post;
    $wp_query->posts             = [ $campaign_post ];
    $wp_query->post              = $campaign_post;
    $wp_query->post_count        = 1;
    $wp_query->found_posts       = 1;
    $wp_query->is_404            = false;
    $wp_query->is_singular       = true;
    $wp_query->is_single         = true;
    $wp_query->queried_object    = $campaign_post;
    $wp_query->queried_object_id = $campaign_post->ID;
    setup_postdata( $post );

    // Força leitura direta do DB, ignorando o Redis Object Cache.
    // O Cache pode ter dado stale após saves com múltiplos hooks (Cart Link,
    // ACF, Normalizer) que escrevem em sequência em frações de segundo.
    clean_post_cache( $campaign_post->ID );
    wp_cache_delete( $campaign_post->ID, 'post_meta' );

    // ── Renderiza o template e encerra (impede o Cart Link de redirecionar) ──
    $template = get_template_directory() . '/single-ic-campaign.php';
    if ( file_exists( $template ) ) {
        // Impede cache no browser E no Nginx FastCGI / Redis full-page cache
        nocache_headers();
        header( 'X-Accel-Expires: 0' );          // Nginx: não armazena
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
        header( 'Surrogate-Control: no-store' );  // proxies/CDN
        include $template;
        die();
    }
}
