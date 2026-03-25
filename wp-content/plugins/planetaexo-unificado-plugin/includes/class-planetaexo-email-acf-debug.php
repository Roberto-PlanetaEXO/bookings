<?php
/**
 * Debug: por que os campos ACF não aparecem no e-mail?
 *
 * Rastreia a cadeia: _id_cartlink → products → pxo_product_acf_mapping → descricao_produto_N / data_da_viagem_N
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PlanetaExoEmailAcfDebug {

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'planetaexo-unificado' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : null;

		echo '<div class="wrap">';
		echo '<h1>Debug: ACF no E-mail</h1>';
		echo '<p>Rastreia por que os campos da proposta (itinerário, data da viagem) não aparecem no e-mail.</p>';

		echo '<form method="get" style="margin: 20px 0;">';
		echo '<input type="hidden" name="page" value="planetaexo-email-acf-debug" />';
		echo '<label>ID do pedido: <input type="number" name="order_id" value="' . esc_attr( (string) $order_id ) . '" min="1" /></label> ';
		echo '<button type="submit" class="button button-primary">Analisar</button>';
		echo '</form>';

		if ( ! $order_id ) {
			echo '<p><em>Digite um ID de pedido e clique em Analisar.</em></p>';
			self::render_recent_orders();
			echo '</div>';
			return;
		}

		if ( ! $order || ! ( $order instanceof WC_Order ) ) {
			echo '<div class="notice notice-error"><p>Pedido #' . esc_html( (string) $order_id ) . ' não encontrado.</p></div>';
			echo '</div>';
			return;
		}

		self::render_diagnostic( $order );
		echo '</div>';
	}

	private static function render_recent_orders() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}
		$orders = wc_get_orders( [
			'limit'   => 10,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'status'   => [ 'processing', 'completed', 'on-hold', 'pending' ],
		] );
		if ( empty( $orders ) ) {
			return;
		}
		echo '<h3>Pedidos recentes</h3><ul>';
		foreach ( $orders as $o ) {
			$id   = $o->get_id();
			$link = add_query_arg( [ 'page' => 'planetaexo-email-acf-debug', 'order_id' => $id ], admin_url( 'admin.php' ) );
			$cart = $o->get_meta( '_id_cartlink' );
			$badge = empty( $cart ) ? ' <span style="color:red">(sem proposta)</span>' : '';
			echo '<li><a href="' . esc_url( $link ) . '">#' . esc_html( (string) $id ) . '</a> ' . esc_html( $o->get_date_created()->date( 'd/m/Y H:i' ) ) . $badge . '</li>';
		}
		echo '</ul>';
	}

	private static function render_diagnostic( WC_Order $order ) {
		$order_id      = $order->get_id();
		$id_cartlink   = $order->get_meta( '_id_cartlink' );
		$campaign_id   = (int) $id_cartlink;

		$steps = [];

		// 1. _id_cartlink no pedido
		$steps[] = [
			'label'  => '1. _id_cartlink no pedido',
			'status' => $campaign_id > 0 ? 'ok' : 'fail',
			'value'  => $campaign_id > 0 ? (string) $campaign_id : '(vazio)',
			'help'   => $campaign_id > 0 ? 'OK — ID da proposta (ic-campaign) está gravado.' : 'FALHA — O pedido não tem _id_cartlink. Verifique PlanetaExoOrderMeta (sessão, carrinho, itens).',
		];

		// 2. _campaign_id nos itens do pedido
		$items_campaign = [];
		foreach ( $order->get_items() as $item ) {
			if ( is_callable( [ $item, 'get_meta' ] ) ) {
				$c = $item->get_meta( '_campaign_id' );
				if ( ! empty( $c ) ) {
					$items_campaign[] = (int) $c;
				}
			}
		}
		$steps[] = [
			'label'  => '2. _campaign_id nos itens do pedido',
			'status' => ! empty( $items_campaign ) ? 'ok' : 'warn',
			'value'  => ! empty( $items_campaign ) ? implode( ', ', array_unique( $items_campaign ) ) : '(nenhum item tem _campaign_id)',
			'help'   => ! empty( $items_campaign ) ? 'OK — Itens têm campaign_id (fallback para _id_cartlink).' : 'Os itens não têm _campaign_id. O copy_campaign_id_to_order_item pode não ter rodado.',
		];

		// 3. Post ic-campaign existe
		$post_exists = $campaign_id > 0 && get_post_type( $campaign_id ) === 'ic-campaign';
		$steps[] = [
			'label'  => '3. Proposta (ic-campaign) existe',
			'status' => $post_exists ? 'ok' : ( $campaign_id > 0 ? 'fail' : 'skip' ),
			'value'  => $campaign_id > 0 ? ( get_post_type( $campaign_id ) ?: '(post não existe)' ) : '—',
			'help'   => $post_exists ? 'OK — O post da proposta existe.' : ( $campaign_id > 0 ? 'FALHA — O post #' . $campaign_id . ' não existe ou não é ic-campaign.' : '—' ),
		];

		// 4. Meta "products" na proposta
		$products_meta = $campaign_id > 0 ? get_post_meta( $campaign_id, 'products', true ) : null;
		$products_ok   = is_array( $products_meta ) && ! empty( $products_meta );
		$steps[] = [
			'label'  => '4. Meta "products" na proposta',
			'status' => $products_ok ? 'ok' : ( $campaign_id > 0 ? 'fail' : 'skip' ),
			'value'  => $products_ok ? count( $products_meta ) . ' produto(s)' : ( $campaign_id > 0 ? '(vazio ou não é array)' : '—' ),
			'help'   => $products_ok ? 'OK — products define o mapeamento produto → slot ACF.' : ( $campaign_id > 0 ? 'FALHA — A proposta não tem products ou está vazio.' : '—' ),
		];

		// 5. pxo_product_acf_mapping
		$acf_mapping = $campaign_id > 0 ? get_post_meta( $campaign_id, 'pxo_product_acf_mapping', true ) : null;
		$mapping_ok  = is_array( $acf_mapping ) || $acf_mapping === '' || $acf_mapping === [];
		$steps[] = [
			'label'  => '5. pxo_product_acf_mapping',
			'status' => $mapping_ok ? 'ok' : ( $campaign_id > 0 ? 'warn' : 'skip' ),
			'value'  => is_array( $acf_mapping ) ? json_encode( $acf_mapping ) : ( (string) $acf_mapping ?: '(vazio)' ),
			'help'   => 'Opcional. Se vazio, usa índice 1:1. Se definido, mapeia posição do produto → slot ACF.',
		];

		// 6. Mapeamento product_id → slot (resultado de pxo_processing_get_products_indexed_by_id)
		$indice_textos = [];
		if ( function_exists( 'pxo_processing_get_products_indexed_by_id' ) && $campaign_id > 0 ) {
			$indice_textos = pxo_processing_get_products_indexed_by_id( $campaign_id );
		}
		$steps[] = [
			'label'  => '6. Mapeamento produto → slot ACF',
			'status' => ! empty( $indice_textos ) ? 'ok' : ( $campaign_id > 0 ? 'fail' : 'skip' ),
			'value'  => ! empty( $indice_textos ) ? json_encode( $indice_textos ) : '(vazio)',
			'help'   => 'Ex: {56488: 1, 82053: 1} = product_id 56488 e variation 82053 usam slot 1 (descricao_produto_1, data_da_viagem_1).',
		];

		// 7. Para cada item do pedido: product_id, variation_id, slot usado
		$items_detail = [];
		foreach ( $order->get_items() as $item ) {
			$pid = $item->get_product_id();
			$vid = $item->get_variation_id();
			$real_id = $vid > 0 ? $vid : $pid;
			$slot = isset( $indice_textos[ $real_id ] ) ? $indice_textos[ $real_id ] : ( isset( $indice_textos[ $pid ] ) ? $indice_textos[ $pid ] : 1 );
			$items_detail[] = "prod {$pid} var {$vid} → slot {$slot}";
		}
		$steps[] = [
			'label'  => '7. Itens do pedido → slot usado',
			'status' => ! empty( $items_detail ) ? 'ok' : 'skip',
			'value'  => implode( ' | ', $items_detail ),
			'help'   => 'Cada item usa descricao_produto_{slot} e data_da_viagem_{slot}.',
		];

		// 8. pxo_field / get_field para slot 1
		$desc_1 = '';
		$data_1 = '';
		if ( $campaign_id > 0 ) {
			if ( function_exists( 'pxo_field' ) ) {
				$desc_1 = pxo_field( 'descricao_produto_1', $campaign_id );
				$data_1 = pxo_field( 'data_da_viagem_1', $campaign_id );
			} else {
				$desc_1 = function_exists( 'get_field' ) ? get_field( 'descricao_produto_1', $campaign_id ) : get_post_meta( $campaign_id, 'descricao_produto_1', true );
				$data_1 = function_exists( 'get_field' ) ? get_field( 'data_da_viagem_1', $campaign_id ) : get_post_meta( $campaign_id, 'data_da_viagem_1', true );
			}
		}
		$has_acf = ( $desc_1 !== '' && $desc_1 !== null ) || ( $data_1 !== '' && $data_1 !== null );
		$steps[] = [
			'label'  => '8. ACF slot 1 (descricao_produto_1, data_da_viagem_1)',
			'status' => $has_acf ? 'ok' : ( $campaign_id > 0 ? 'fail' : 'skip' ),
			'value'  => $campaign_id > 0 ? 'desc: ' . ( $desc_1 ? substr( strip_tags( (string) $desc_1 ), 0, 80 ) . '…' : '(vazio)' ) . ' | data: ' . ( $data_1 ?: '(vazio)' ) : '—',
			'help'   => $has_acf ? 'OK — Os campos ACF existem na proposta.' : ( $campaign_id > 0 ? 'FALHA — descricao_produto_1 ou data_da_viagem_1 estão vazios na proposta.' : '—' ),
		];

		// 9. pxo_field e pxo_format_date existem
		$steps[] = [
			'label'  => '9. Funções pxo_field e pxo_format_date',
			'status' => ( function_exists( 'pxo_field' ) && function_exists( 'pxo_format_date' ) ) ? 'ok' : 'fail',
			'value'  => 'pxo_field: ' . ( function_exists( 'pxo_field' ) ? 'sim' : 'não' ) . ' | pxo_format_date: ' . ( function_exists( 'pxo_format_date' ) ? 'sim' : 'não' ),
			'help'   => 'Precisam existir no tema (functions.php) para o e-mail exibir os ACF.',
		];

		// Render
		echo '<table class="widefat fixed striped" style="max-width: 900px; margin-top: 20px;">';
		echo '<thead><tr><th style="width:30%">Etapa</th><th>Resultado</th><th>Ajuda</th></tr></thead><tbody>';
		foreach ( $steps as $s ) {
			$status_class = $s['status'] === 'ok' ? 'color:green' : ( $s['status'] === 'fail' ? 'color:red' : ( $s['status'] === 'warn' ? 'color:orange' : 'color:#666' ) );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $s['label'] ) . '</strong><br><span style="' . esc_attr( $status_class ) . '">' . esc_html( strtoupper( $s['status'] ) ) . '</span></td>';
			echo '<td><code style="word-break:break-all;">' . esc_html( $s['value'] ) . '</code></td>';
			echo '<td>' . esc_html( $s['help'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// Link para editar proposta
		if ( $campaign_id > 0 && $post_exists ) {
			$edit_url = admin_url( 'post.php?post=' . $campaign_id . '&action=edit' );
			echo '<p style="margin-top: 20px;"><a href="' . esc_url( $edit_url ) . '" class="button">Editar proposta #' . esc_html( (string) $campaign_id ) . '</a></p>';
		}
	}
}
