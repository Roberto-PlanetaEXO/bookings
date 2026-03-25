<?php
/**
 * Admin PIX receipt sent — notificação de comprovante enviado
 *
 * Este template pode ser sobrescrito copiando para yourtheme/woocommerce/emails/admin-pix-receipt-sent.php
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 4.0.0
 */

use Piggly\WooPixGateway\Core\Entities\PixEntity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var PixEntity $pix */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p><?php esc_html_e( 'Olá,', 'wc-piggly-pix' ); ?></p>
<p><?php printf( esc_html__( 'O consumidor enviou o comprovante de pagamento para o pedido #%s.', 'wc-piggly-pix' ), esc_html( $order->get_order_number() ) ); ?></p>
<p><?php echo wp_kses_post( sprintf( __( '<a href="%s">Clique aqui</a> para visualizar o comprovante', 'wc-piggly-pix' ), esc_url( $order->get_edit_order_url() ) ) ); ?></p>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
