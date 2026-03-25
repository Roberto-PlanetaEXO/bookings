<?php
/**
 * PlanetaExo — Debug de Mapeamento Produto ↔ Campo ACF
 *
 * Exibe um overlay flutuante no frontend da proposta quando:
 *   - O usuário está logado com capacidade manage_options
 *   - A URL contém ?pxo_debug=1
 *
 * Exemplo: https://seusite.com/nome-da-proposta/?pxo_debug=1
 *
 * Mostra:
 *   - Post ID e slug
 *   - Array 'products' direto do DB (normalizado e raw)
 *   - pxo_product_acf_mapping salvo
 *   - Para cada produto: posição, produto WC, slot ACF resolvido, valores dos campos
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoMappingDebug {

    public static function init(): void {
        // Frontend — injeta no template quando ?pxo_debug=1
        add_action( 'wp_footer', [ __CLASS__, 'maybe_render_footer' ], 999 );
    }

    /**
     * Chamado diretamente ao final do single-ic-campaign.php
     * (útil porque o template usa die() e wp_footer pode não disparar)
     */
    public static function maybe_render( int $post_id ): void {
        // Só para admins autenticados
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( empty( $_GET['pxo_debug'] ) || $_GET['pxo_debug'] !== '1' ) return;

        self::render( $post_id );
    }

    /** Fallback via wp_footer (para outros contextos) */
    public static function maybe_render_footer(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( empty( $_GET['pxo_debug'] ) || $_GET['pxo_debug'] !== '1' ) return;

        $post_id = get_queried_object_id() ?: get_the_ID();
        if ( ! $post_id ) return;

        self::render( $post_id );
    }

    /** Renderiza o overlay de debug */
    private static function render( int $post_id ): void {
        if ( ! $post_id ) return;

        $post = get_post( $post_id );
        if ( ! $post ) return;

        // ── Leitura direta do DB (ignora cache) ────────────────────────
        wp_cache_delete( $post_id, 'post_meta' );
        $products_raw = get_post_meta( $post_id, 'products', true );
        $acf_mapping  = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );

        if ( ! is_array( $products_raw ) ) $products_raw = [];
        if ( ! is_array( $acf_mapping ) )  $acf_mapping  = [];

        // ── Detecta normalização ────────────────────────────────────────
        $keys              = array_keys( $products_raw );
        $already_normalized = ! empty( $keys ) && ( $keys[0] === '1' || $keys[0] === 1 );
        $products_indexed  = $already_normalized
            ? $products_raw
            : array_combine( range( 1, count( $products_raw ) ), array_values( $products_raw ) );

        // ── Resolve slot ACF + lê valores ──────────────────────────────
        $rows = [];
        foreach ( $products_indexed as $position => $item ) {
            $position  = (int) $position;
            $acf_slot  = isset( $acf_mapping[ $position ] ) ? (int) $acf_mapping[ $position ] : $position;
            $pid       = absint( $item['product_id'] ?? 0 );
            $wc_name   = '';
            if ( $pid && function_exists( 'wc_get_product' ) ) {
                $wc = wc_get_product( $pid );
                $wc_name = $wc ? $wc->get_name() : "#{$pid} (não encontrado)";
            }
            $desc_val  = get_post_meta( $post_id, 'descricao_produto_' . $acf_slot, true );
            $date_val  = get_post_meta( $post_id, 'data_da_viagem_'    . $acf_slot, true );
            $rows[] = [
                'pos'       => $position,
                'wc_pid'    => $pid,
                'wc_name'   => $wc_name,
                'qty'       => $item['quantity'] ?? '?',
                'prod_key'  => $item['product_id'] ?? '?',
                'acf_slot'  => $acf_slot,
                'mapped'    => isset( $acf_mapping[ $position ] ),
                'desc_key'  => 'descricao_produto_' . $acf_slot,
                'date_key'  => 'data_da_viagem_'    . $acf_slot,
                'desc_val'  => $desc_val ?: '(vazio)',
                'date_val'  => $date_val ?: '(vazio)',
            ];
        }

        // ── Mapeamento invertido: slot → posições ──────────────────────
        $inverted = [];
        foreach ( $acf_mapping as $pos => $slot ) {
            $inverted[ (int) $slot ][] = (int) $pos;
        }
        $duplicate_slots = [];
        foreach ( $inverted as $slot => $positions ) {
            if ( count( $positions ) > 1 ) {
                $duplicate_slots[] = $slot;
            }
        }

        // ── Campos ACF disponíveis no DB (slots 1-10) ──────────────────
        $all_acf_slots = [];
        for ( $n = 1; $n <= 10; $n++ ) {
            $d = get_post_meta( $post_id, 'descricao_produto_' . $n, true );
            $t = get_post_meta( $post_id, 'data_da_viagem_'    . $n, true );
            if ( $d || $t ) {
                $all_acf_slots[] = [
                    'slot' => $n,
                    'desc' => $d ?: '(vazio)',
                    'date' => $t ?: '(vazio)',
                ];
            }
        }

        $has_error = ! empty( $duplicate_slots );
        $border    = $has_error ? '#c62828' : '#0097a7';
        ?>
        <style>
        #pxo-mapping-debug {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 999999;
            max-height: 60vh;
            overflow-y: auto;
            background: #1a1a2e;
            color: #e0e0e0;
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 12px;
            border-top: 3px solid <?php echo $border; ?>;
            padding: 0;
        }
        #pxo-mapping-debug .pxo-dbg-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: <?php echo $border; ?>;
            color: #fff;
            padding: 6px 14px;
            font-weight: bold;
            font-size: 13px;
            cursor: pointer;
            user-select: none;
            position: sticky;
            top: 0;
        }
        #pxo-mapping-debug .pxo-dbg-body { padding: 12px 16px 16px; }
        #pxo-mapping-debug h4 {
            color: #26c6da;
            margin: 12px 0 6px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid #333;
            padding-bottom: 3px;
        }
        #pxo-mapping-debug table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 8px;
        }
        #pxo-mapping-debug th {
            background: #0d3b52;
            color: #80d8ff;
            padding: 4px 8px;
            text-align: left;
            font-size: 11px;
            white-space: nowrap;
        }
        #pxo-mapping-debug td {
            padding: 4px 8px;
            border-bottom: 1px solid #2a2a3e;
            vertical-align: top;
        }
        #pxo-mapping-debug tr:hover td { background: #1e1e3a; }
        #pxo-mapping-debug .ok    { color: #69f0ae; }
        #pxo-mapping-debug .warn  { color: #ffd740; }
        #pxo-mapping-debug .err   { color: #ff5252; }
        #pxo-mapping-debug .muted { color: #616161; }
        #pxo-mapping-debug .badge-slot {
            display: inline-block;
            background: #01579b;
            color: #80d8ff;
            padding: 1px 7px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }
        #pxo-mapping-debug .badge-inferred {
            background: #4a148c;
            color: #ea80fc;
        }
        #pxo-mapping-debug .pxo-dbg-meta {
            background: #111;
            padding: 6px 10px;
            border-radius: 4px;
            word-break: break-all;
            font-size: 11px;
            color: #aaa;
            margin-top: 4px;
        }
        #pxo-mapping-debug .pxo-dbg-raw {
            background: #0a0a1a;
            padding: 8px 10px;
            border-radius: 4px;
            font-size: 11px;
            overflow-x: auto;
            white-space: pre-wrap;
            color: #b0bec5;
        }
        </style>

        <div id="pxo-mapping-debug">
            <div class="pxo-dbg-header" onclick="document.getElementById('pxo-dbg-body').style.display = document.getElementById('pxo-dbg-body').style.display === 'none' ? 'block' : 'none'">
                🔍 PXO DEBUG — Mapeamento Produto ↔ ACF
                <span style="font-size:11px; font-weight:normal;">
                    Post ID: <?php echo $post_id; ?> | Slug: <strong><?php echo esc_html( $post->post_name ); ?></strong>
                    <?php if ( $has_error ) echo ' | <span style="color:#ffcdd2">⚠ SLOTS DUPLICADOS: ' . implode(', ', $duplicate_slots) . '</span>'; ?>
                    &nbsp;▲▼
                </span>
            </div>
            <div class="pxo-dbg-body" id="pxo-dbg-body">

                <?php /* ── Bloco 1: informações gerais ── */ ?>
                <h4>📋 Informações Gerais</h4>
                <table>
                    <tr><th>Campo</th><th>Valor</th></tr>
                    <tr><td>Post ID</td><td><?php echo $post_id; ?></td></tr>
                    <tr><td>Slug</td><td><?php echo esc_html($post->post_name); ?></td></tr>
                    <tr><td>Produtos no array</td><td><?php echo count($products_indexed); ?></td></tr>
                    <tr><td>Normalizado?</td><td><?php echo $already_normalized ? '<span class="ok">✓ Sim (chaves "1","2","3"...)</span>' : '<span class="warn">⚠ NÃO — UUID ou outros</span>'; ?></td></tr>
                    <tr><td>pxo_product_acf_mapping</td><td><?php echo $acf_mapping ? '<span class="ok">✓ Existe: ' . esc_html(json_encode($acf_mapping)) . '</span>' : '<span class="muted">— Não configurado (usando posição como slot)</span>'; ?></td></tr>
                    <?php if ( $duplicate_slots ) : ?>
                    <tr><td colspan="2" class="err">⚠ SLOTS ACF DUPLICADOS: <?php echo implode(', ', $duplicate_slots); ?> — dois produtos apontam para o mesmo campo!</td></tr>
                    <?php endif; ?>
                </table>

                <?php /* ── Bloco 2: tabela de resolução ── */ ?>
                <h4>🔗 Resolução: Produto → Slot ACF → Valores</h4>
                <table>
                    <tr>
                        <th>#</th>
                        <th>Produto (WC)</th>
                        <th>PID</th>
                        <th>Qty</th>
                        <th>Slot ACF</th>
                        <th>Campo descrição lido</th>
                        <th>descricao_produto_N (100 chars)</th>
                        <th>Campo data lido</th>
                        <th>data_da_viagem_N</th>
                    </tr>
                    <?php foreach ( $rows as $r ) :
                        $slot_class = $r['mapped'] ? 'badge-slot' : 'badge-slot badge-inferred';
                        $slot_title = $r['mapped'] ? 'mapeado pelo Linker' : 'inferido (sem vínculo → usa posição)';
                        $desc_short = mb_strimwidth( strip_tags( (string) $r['desc_val'] ), 0, 100, '...' );
                    ?>
                    <tr>
                        <td><?php echo $r['pos']; ?></td>
                        <td style="max-width:200px; word-break:break-word;"><?php echo esc_html($r['wc_name']); ?></td>
                        <td><?php echo $r['wc_pid']; ?></td>
                        <td><?php echo esc_html($r['qty']); ?></td>
                        <td title="<?php echo esc_attr($slot_title); ?>">
                            <span class="<?php echo $slot_class; ?>"><?php echo $r['acf_slot']; ?></span>
                            <?php if ( ! $r['mapped'] ) echo '<br><span class="muted" style="font-size:10px;">(sem vínculo)</span>'; ?>
                        </td>
                        <td><code><?php echo esc_html($r['desc_key']); ?></code></td>
                        <td style="max-width:250px; word-break:break-word;"><?php echo esc_html($desc_short); ?></td>
                        <td><code><?php echo esc_html($r['date_key']); ?></code></td>
                        <td><?php echo esc_html($r['date_val']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>

                <?php /* ── Bloco 3: todos os campos ACF com valor no DB ── */ ?>
                <h4>📦 Todos os slots ACF com dados no DB (descricao/data 1-10)</h4>
                <?php if ( empty($all_acf_slots) ) : ?>
                <p class="muted">Nenhum campo descricao_produto_N ou data_da_viagem_N com valor encontrado.</p>
                <?php else : ?>
                <table>
                    <tr>
                        <th>Slot</th>
                        <th>descricao_produto_N (100 chars)</th>
                        <th>data_da_viagem_N</th>
                        <th>Usado por</th>
                    </tr>
                    <?php foreach ( $all_acf_slots as $s ) :
                        $used_by = isset( $inverted[ $s['slot'] ] )
                            ? 'Produto(s) posição: ' . implode(', ', $inverted[ $s['slot'] ])
                            : '<span class="muted">—</span>';
                        $desc_short = mb_strimwidth( strip_tags( (string) $s['desc'] ), 0, 100, '...' );
                    ?>
                    <tr>
                        <td><span class="badge-slot"><?php echo $s['slot']; ?></span></td>
                        <td style="max-width:300px; word-break:break-word;"><?php echo esc_html($desc_short); ?></td>
                        <td><?php echo esc_html($s['date']); ?></td>
                        <td><?php echo $used_by; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php endif; ?>

                <?php /* ── Bloco 4: raw dos products ── */ ?>
                <h4>🗄 products (raw do DB)</h4>
                <div class="pxo-dbg-raw"><?php echo esc_html( json_encode( $products_raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></div>

                <?php /* ── Bloco 5: como remover o debug ── */ ?>
                <p style="color:#546e7a; font-size:11px; margin-top:10px;">
                    ℹ Remove <code>?pxo_debug=1</code> da URL para ocultar este painel.
                    Visível apenas para usuários com <code>manage_options</code>.
                </p>
            </div>
        </div>
        <?php
    }
}
