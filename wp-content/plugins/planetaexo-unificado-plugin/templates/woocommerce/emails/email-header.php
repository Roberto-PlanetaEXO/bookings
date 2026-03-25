<?php
/**
 * Email Header — PlanetaExo (override via plugin)
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package PlanetaExo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pxo_logo = get_option( 'woocommerce_email_header_image' );
if ( empty( $pxo_logo ) ) {
	$pxo_plugin_root = dirname( __DIR__, 3 );
	$pxo_logo_path   = $pxo_plugin_root . '/assets/images/header_email_planetaexo.png';
	if ( file_exists( $pxo_logo_path ) ) {
		$pxo_logo = plugin_dir_url( $pxo_plugin_root . '/planetaexo-unificado.php' ) . 'assets/images/header_email_planetaexo.png';
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<title><?php echo esc_html( get_bloginfo( 'name', 'display' ) ); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0">
		<table width="100%" id="outer_wrapper">
			<tr>
				<td></td>
				<td width="600">
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
							<tr>
								<td align="center" valign="top">
									<div id="template_header_image">
										<?php if ( $pxo_logo ) : ?>
											<p style="margin-top:0;"><img src="<?php echo esc_url( $pxo_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" style="border:none;display:inline-block;height:auto;max-width:100%;margin-left:0;margin-right:0" /></p>
										<?php endif; ?>
									</div>
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" style="background-color:#fff;border:1px solid #dedede;border-radius:3px" bgcolor="#fff">
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header">
													<tr>
														<td id="header_wrapper">
															<h1><?php echo esc_html( $email_heading ); ?></h1>
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body">
													<tr>
														<td valign="top" id="body_content">
															<table border="0" cellpadding="20" cellspacing="0" width="100%">
																<tr>
																	<td valign="top">
																		<div id="body_content_inner">
