<?php
/**
 * Admin new order email — layout customizado PlanetaEXO
 *
 * Inclui campos ACF (descricao_produto, data_da_viagem) e formatação alinhada
 * aos demais e-mails do tema (customer-processing-order, admin-failed-order).
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/admin-new-order.php
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 9.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Garante $order e variáveis para o template (e-mail vai para o admin).
if ( isset( $email ) && is_a( $email, 'WC_Email_New_Order' ) ) {
	$order = $email->object;
} elseif ( ! isset( $order ) || ! is_a( $order, 'WC_Order' ) ) {
	return;
}
$additional_content = isset( $additional_content ) ? $additional_content : '';
$sent_to_admin     = isset( $sent_to_admin ) ? $sent_to_admin : true;
$plain_text        = isset( $plain_text ) ? $plain_text : false;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">

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
																esc_html__( 'You have received the following order from %s:', 'woocommerce' ),
																esc_html( $order->get_formatted_billing_full_name() )
															);
															?>
															<br />
															<span style="font-size: 25px; line-height: 25px;">
																<?php esc_html_e( 'Order number', 'woocommerce' ); ?>: <strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
															</span>
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

<?php do_action( 'woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email ); ?>

<div style="margin-bottom: 40px;">
	<table class="td" cellspacing="0" cellpadding="0" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; background: #fff" border="0">
		<?php
		// Usa tabela customizada com ACF (descricao_produto, data_da_viagem) — evita duplicação do layout padrão.
		echo pxo_admin_new_order_custom_email_order_items_table( $order, array( 'show_sku' => $sent_to_admin, 'show_image' => false, 'plain_text' => $plain_text, 'sent_to_admin' => $sent_to_admin ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
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
					<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wp_kses_post( $total['value'] ); ?></td>
				</tr>
				<?php
			}
		}
		if ( $order->get_customer_note() ) {
			?>
			<tr style="border-bottom: 1px solid #eaebed4f; border-top: 1px solid #eaebed4f;">
				<td style="padding-left: 48px; padding-top: 10px; padding-bottom: 10px; border-left: 0; border-right: 0;"><?php esc_html_e( 'Note', 'woocommerce' ); ?></td>
				<td style="padding-right: 48px; padding-bottom: 5px; text-align: right; border-left: 0; border-right: 0;"><?php echo wp_kses( nl2br( wptexturize( $order->get_customer_note() ) ), array() ); ?></td>
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
?>
