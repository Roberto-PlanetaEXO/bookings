<!-- DEBUG: Template form-checkout.php customizado CARREGADO! -->
<?php
/**
 * Checkout Form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/checkout/form-checkout.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_checkout_form', $checkout );

// If checkout registration is disabled and not logged in, the user cannot checkout.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}

?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

	<?php if ( $checkout->get_checkout_fields() ) : ?>

		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<div class="col2-set" id="customer_details">
			<div class="col-1">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
			</div>

			<div class="col-2">
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
		</div>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

	<?php endif; ?>

	<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

	<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

	<div id="order_review" class="woocommerce-checkout-review-order" style="width:100%;min-width:0;">
        <div class="order-details-wrapper" style="width:100%!important;max-width:100%!important;">
            <h3 id="order_review_heading"><?php esc_html_e( 'Booking Summary', 'woocommerce' ); ?></h3>
            <?php do_action( 'woocommerce_checkout_order_review' ); ?>
        </div>
	</div>

	<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

<script>
	var rodou_execucao = false;

	document.addEventListener("DOMContentLoaded", function () {
		waitForSelect2AndRun(ocultaCamposPassaporteCPF);
	});

	// Aguarda o Select2 estar disponível antes de rodar a função
	function waitForSelect2AndRun(callback, interval = 100, maxTries = 30) {
		let tries = 0;
		const timer = setInterval(() => {
			if (jQuery.fn.select2 && jQuery('#billing_country').data('select2')) {
				clearInterval(timer);
				callback();
			} else if (++tries >= maxTries) {
				clearInterval(timer);
				console.warn('Select2 não carregou a tempo.');
			}
		}, interval);
	}

	function ocultaCamposPassaporteCPF() {
		const cpfLabel = document.querySelector("label[for='billing_cpf']");

		if (!cpfLabel) {
			console.warn('Label do CPF não encontrado.');
			return;
		}

		function atualizarLabelCPF() {
			const selectedCountry = jQuery('#billing_country').val();

			if (selectedCountry !== "BR") {
				cpfLabel.innerHTML = "Passport Number&nbsp;<span class='required' aria-hidden='true'>*</span>";
				jQuery('#billing_cpf, #credit-card-cpf').unmask();
			} else {
				cpfLabel.innerHTML = "CPF&nbsp;<span class='required' aria-hidden='true'>*</span>";
				jQuery('#billing_cpf, #credit-card-cpf').mask('000.000.000-00');
			}
		}

		// Atualiza imediatamente ao carregar
		atualizarLabelCPF();

		// Atualiza ao mudar o país
		jQuery('#billing_country').on('change select2:select', atualizarLabelCPF);

		// Força evento para capturar preenchimento automático do navegador
		setTimeout(() => {
			jQuery('#billing_country').trigger('change');
		}, 200);
	}
</script>

<style>
	.woocommerce-checkout .select2-container .select2-selection--single .select2-selection__rendered {
		bottom: auto !important;
	}

	.woocommerce-checkout .select2-container--default .select2-selection--single {
		padding-bottom: 0.0em !important;
		padding-top: 0.0em !important;
	}
</style>
