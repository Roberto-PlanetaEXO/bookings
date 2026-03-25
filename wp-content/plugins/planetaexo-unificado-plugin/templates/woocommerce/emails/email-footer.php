<?php
/**
 * Email Footer — PlanetaExo (override via plugin)
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package PlanetaExo
 */

defined( 'ABSPATH' ) || exit;
?>
																		</div>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									<table border="0" cellpadding="10" cellspacing="0" width="100%" id="template_footer">
										<tr>
											<td valign="top">
												<table border="0" cellpadding="10" cellspacing="0" width="100%">
													<tr>
														<td colspan="2" valign="middle" id="credit" class="rodapeEmail" style="border-radius:6px;border:0;font-family:Helvetica Neue,Helvetica,Roboto,Arial,sans-serif;font-size:12px;line-height:150%;text-align:center;padding:24px 0;color:#3c3c3c">
															<style>.rodapeEmail p{font-family:Helvetica;font-weight:normal;font-size:16px!important;line-height:25px;letter-spacing:0}</style>
															<?php
															echo wp_kses_post(
																wpautop(
																	wptexturize(
																		apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) )
																	)
																)
															);
															?>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</div>
				</td>
				<td></td>
			</tr>
		</table>
	</body>
</html>
