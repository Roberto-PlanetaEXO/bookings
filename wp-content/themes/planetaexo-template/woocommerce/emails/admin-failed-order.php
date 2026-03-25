<?php
/**
 * Admin failed order email — layout customizado PlanetaEXO
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-failed-order.php
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
//do_action( 'woocommerce_email_header', $email_heading, $email );

?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<title><?php echo get_bloginfo( 'name', 'display' ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">

	<?php
	// Garante $order e variáveis para o template (e-mail vai para o admin).
	if ( isset( $email ) && is_a( $email, 'WC_Email_Failed_Order' ) ) {
		$order = $email->object;
	} elseif ( ! isset( $order ) || ! is_a( $order, 'WC_Order' ) ) {
		return;
	}
	$additional_content = isset( $additional_content ) ? $additional_content : '';
	?>

	<style>
	ol, ul { list-style: disc; margin: 0 0 1.25em 1.5em; }
	li { display: list-item; margin: 0; }
	.rodape p { font-family: Helvetica; font-weight: 300; font-size: 36px; line-height: 46px; letter-spacing: 0px; text-align: center; }
	#template_container { box-shadow: none !important; border-radius: 0px !important; }
	.email-itinerary img {
		width: 100% !important;
		max-width: 100% !important;
		height: 220px !important;
		object-fit: cover !important;
		object-position: center;
		display: block !important;
		margin: 0;
		border-radius: 0;
	}
	.email-itinerary h1, .email-itinerary h2, .email-itinerary h3 {
		font-family: Helvetica, Arial, sans-serif;
		font-weight: 600;
		margin: 1em 0 0.5em;
		line-height: 1.3;
	}
	@media screen and (max-width: 600px) {
		.pxo-email-container { width: 100% !important; max-width: 100% !important; }
		#wrapper { padding: 20px 0 !important; }
		#header_wrapper { padding: 20px 16px !important; }
		.email-itinerary, .td strong, .td p, .td table td, .pxo-order-totals td { padding-left: 16px !important; padding-right: 16px !important; }
		.pxo-email-img-wrap { margin: 16px 0 !important; width: 100% !important; max-width: 100% !important; }
		.pxo-order-totals { table-layout: fixed !important; }
	}
	</style>

	<table width="100%" id="outer_wrapper">
		<tr>
			<td></td>
			<td width="600" class="pxo-email-container" style="max-width: 100%;">
				<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
					<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" style="border: none;">
						<tr>
							<td align="center" valign="top">
								<div id="template_header_image">
									<?php
									$img = get_option( 'woocommerce_email_header_image' );
									if ( empty( $img ) && file_exists( get_template_directory() . '/assets/images/header_email_planetaexo.png' ) ) {
										$img = get_template_directory_uri() . '/assets/images/header_email_planetaexo.png';
									}
									if ( $img ) {
										echo '<p style="margin-top:0;"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '" style="border:none;display:inline-block;height:auto;max-width:100%;margin-left:0;margin-right:0" /></p>';
									}
									?>
								</div>
								<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" style="background: transparent; border: none; box-shadow: none;">
									<tr>
										<td align="center" valign="top">
											<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header">
												<tr>
													<td id="header_wrapper">
														<style>
														.cabecalho { text-align: center; padding: 0 0.8em; font-family: Helvetica; font-weight: 300; font-size: 36px; line-height: 30px; letter-spacing: 0px; margin-top: 20px; margin-bottom: 20px; }
														</style>
														<h1 class="cabecalho">
															<?php
															printf(
																esc_html__( 'Unfortunately, the payment for order #%1$s from %2$s has failed. The order was as follows:', 'woocommerce' ),
																esc_html( $order->get_order_number() ),
																esc_html( $order->get_formatted_billing_full_name() )
															);
															?>
														</h1>
													</td>
												</tr>
											</table>
										</td>
									</tr>
									<tr>
										<td align="center" valign="top">
											<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
												<tr>
													<td valign="top" id="body_content" style="background: transparent">
														<table border="0" cellpadding="20" cellspacing="0" width="100%" class="emailConfirmacao" style="margin-top: 40px; background: transparent">
															<tr>
																<td valign="top">
																	<div id="body_content_inner">

<?php
if ( ! function_exists( 'pxo_admin_failed_get_products_indexed_by_id' ) ) {
	function pxo_admin_failed_get_products_indexed_by_id( $post_id ) {
		$serialized_data = get_post_meta( $post_id, 'products', true );
		$result = array();
		$index = 1;
		if ( $serialized_data && is_array( $serialized_data ) ) {
			foreach ( $serialized_data as $product ) {
				if ( isset( $product['product_id'] ) ) {
					$result[ $product['product_id'] ] = $index;
					$index++;
				}
			}
		}
		return $result;
	}
}

if ( ! function_exists( 'pxo_admin_failed_custom_email_order_items_table' ) ) {
	function pxo_admin_failed_custom_email_order_items_table( $order, $args = array() ) {
		$args   = wp_parse_args( $args, array( 'show_sku' => false, 'show_image' => false, 'image_size' => array( 32, 32 ), 'plain_text' => false, 'sent_to_admin' => false ) );
		$items  = $order->get_items();
		$_id    = $order->get_meta( '_id_cartlink' );
		$idx    = pxo_admin_failed_get_products_indexed_by_id( $_id );

		ob_start();
		foreach ( $items as $item_id => $item ) :
			$product       = $item->get_product();
			$purchase_note = $product ? $product->get_purchase_note() : '';
			$product_id    = $item->get_product_id();
			$variation_id  = $item->get_variation_id();
			$real_id       = $variation_id > 0 ? $variation_id : $product_id;
			$default_lang  = function_exists( 'wpml_get_default_language' ) ? wpml_get_default_language() : null;
			$real_id       = apply_filters( 'wpml_object_id', $real_id, 'product', true, $default_lang );
			$cont          = isset( $idx[ $real_id ] ) ? $idx[ $real_id ] : 1;
			?>
			<tr>
				<td style="text-align:left; border: 0;">
					<strong style="font-family: Helvetica; font-weight: 700; font-size: 25px; line-height: 25px; letter-spacing: 0px; padding: 30px 48px 0px 48px; display: block;"><?php echo esc_html( $item->get_name() ); ?></strong>
					<?php
					if ( ! empty( $_id ) ) {
						$itinerario    = get_field( 'descricao_produto_' . $cont, $_id );
						$data_viagem   = get_field( 'data_da_viagem_' . $cont, $_id );
						if ( $data_viagem != '' ) {
							echo '<p style="margin-top: 1em; font-family: Helvetica; font-weight: 400; font-size: 18px; line-height: 20px; padding: 0 48px; display: block;">';
							esc_attr_e( 'Start Date', 'woocommerce' );
							echo ': ' . esc_html( $data_viagem ) . '</p>';
						}
						if ( $itinerario ) {
							$itinerario = function_exists( 'pxo_email_sanitize_itinerary_html' ) ? pxo_email_sanitize_itinerary_html( $itinerario ) : $itinerario;
							echo '<div class="email-itinerary" style="font-family: Helvetica, Arial, sans-serif; font-size: 16px; line-height: 25px; letter-spacing: 0; color: #3c3c3c; padding: 15px 48px 60px 48px; display: block;">' . wp_kses_post( $itinerario ) . '</div>';
						}
					}
					?>
					<table cellspacing="0" cellpadding="6" border="1" width="100%" style="border: 0;">
						<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
							<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php echo esc_html__( 'Quantity', 'woocommerce' ); ?></td>
							<td style="padding-right: 48px; padding-bottom: 5px; text-align: right;"><?php echo esc_html( $item->get_quantity() ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
							<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px;"><?php echo esc_html__( 'Price per person', 'woocommerce' ); ?></td>
							<td style="padding-right: 48px; padding-bottom: 5px; text-align: right;"><?php echo wc_price( $order->get_item_total( $item, false, true ) ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
							<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px;"><?php echo esc_html__( 'Total Price', 'woocommerce' ); ?></td>
							<td style="padding-right: 48px; padding-bottom: 5px; text-align: right;"><?php echo wc_price( $order->get_item_total( $item, false, true ) * $item->get_quantity() ); ?></td>
						</tr>
					</table>
				</td>
			</tr>
			<?php if ( $purchase_note ) : ?>
			<tr>
				<td colspan="3" style="text-align:left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo wp_kses_post( wpautop( do_shortcode( $purchase_note ) ) ); ?></td>
			</tr>
			<?php endif; ?>
			<?php
		endforeach;
		return ob_get_clean();
	}
}

do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email );
?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="0" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; background: #fff" border="0">
		<?php echo pxo_admin_failed_custom_email_order_items_table( $order, array( 'show_sku' => $sent_to_admin, 'show_image' => false, 'plain_text' => $plain_text, 'sent_to_admin' => $sent_to_admin ) ); ?>
	</table>

	<?php
	$formatted_date = function_exists( 'pxo_get_order_datetime_formatted' ) ? pxo_get_order_datetime_formatted( $order ) : date_i18n( 'l, M j Y \a\t g:i A', $order->get_date_created() ? $order->get_date_created()->getTimestamp() : time() );
	?>
	<p style="font-family: Helvetica; font-weight: 400; font-size: 16px; line-height: 20px; text-align: center; color: #9A9EA6; margin-top: 30px;"><?php echo esc_html( $formatted_date ); ?></p>

	<table cellspacing="0" cellpadding="6" border="1" width="100%" style="border: 0; background: #fff">
		<?php
		$item_totals = $order->get_order_item_totals();
		if ( $item_totals ) {
			$i = 0;
			$n = count( $item_totals );
			foreach ( $item_totals as $total ) {
				$i++;
				$style = $i === $n ? 'border-top: 1px solid black; font-weight: bold; font-size: 16px;' : 'border-top: 1px solid #eaebed4f;';
				?>
				<tr style="border-bottom: 1px solid #eaebed4f; <?php echo esc_attr( $style ); ?>">
					<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php echo wp_kses_post( str_replace( ':', '', $total['label'] ) ); ?></td>
					<td style="padding-right: 48px; padding-bottom: 5px; text-align: right;"><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
				<?php
			}
		}
		if ( $order->get_customer_note() ) {
			?>
			<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
				<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px;"><?php esc_html_e( 'Note', 'woocommerce' ); ?></td>
				<td style="padding-right: 48px; padding-bottom: 5px; text-align: right;"><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), array() ); ?></td>
			</tr>
			<?php
		}
		?>
	</table>
</div>

<?php do_action( 'woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<?php
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( ! empty( $additional_content ) ) {
	echo '<div class="rodape">';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo '</div>';
}

do_action( 'woocommerce_email_footer', $email );
