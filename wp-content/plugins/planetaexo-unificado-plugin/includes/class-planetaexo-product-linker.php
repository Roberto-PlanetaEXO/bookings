<?php
/**
 * PlanetaExo - Vincula produtos do Cart Link aos campos "Proposta: Informacoes sobre o Produto N"
 *
 * Cria um metabox PHP proprio "Vinculos de Produto <-> Campo ACF" na edicao de ic-campaign.
 * Para cada produto cadastrado no Cart Link (meta 'products'), exibe
 * uma linha com:
 *   - Nome do produto WooCommerce
 *   - Select: "Proposta: Informacoes sobre o Produto 1" ... "10"
 *   - Botao "Salvar" (AJAX, por linha)
 *
 * O mapeamento e salvo em pxo_product_acf_mapping:
 *   { "1": 3, "2": 1 }
 *   chave = posicao na tabela Cart Link (1,2,3...), valor = slot ACF (1-10)
 *
 * Badge nos metaboxes ACF usa o mapeamento salvo.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoProductLinker {

    public static function init(): void {
        add_action( 'add_meta_boxes',               [ __CLASS__, 'register_metabox'  ] );
        add_action( 'admin_footer',                 [ __CLASS__, 'inject_badges_js'  ] );
        add_action( 'admin_enqueue_scripts',        [ __CLASS__, 'enqueue_assets'    ] );
        add_action( 'wp_ajax_pxo_save_acf_mapping', [ __CLASS__, 'ajax_save_mapping' ] );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'ic-campaign' ) return;
        // jQuery ja e carregado pelo WP admin — nada extra necessario
    }

    /** AJAX: salva vinculo posicao -> slot ACF */
    public static function ajax_save_mapping(): void {
        // Retorna JSON 200 mesmo em erro — jQuery .fail() so dispara em falha de rede
        if ( ! check_ajax_referer( 'pxo_acf_mapping', 'nonce', false ) ) {
            wp_send_json_error( [ 'msg' => 'Nonce invalido. Atualize a pagina e tente novamente.' ] );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'msg' => 'Sem permissao para editar.' ] );
        }
        $post_id  = absint( $_POST['post_id']  ?? 0 );
        $position = absint( $_POST['position'] ?? 0 );
        $slot     = absint( $_POST['slot']     ?? 0 );
        if ( ! $post_id || ! $position || $slot < 0 || $slot > 10 ) {
            wp_send_json_error( [ 'msg' => "Parametros invalidos: post={$post_id} pos={$position} slot={$slot}" ] );
        }
        $mapping = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );
        if ( ! is_array( $mapping ) ) $mapping = [];
        if ( $slot === 0 ) {
            unset( $mapping[ (string) $position ] );
        } else {
            $mapping[ (string) $position ] = $slot;
        }
        update_post_meta( $post_id, 'pxo_product_acf_mapping', $mapping );
        wp_send_json_success( [ 'msg' => 'Vinculo salvo com sucesso', 'mapping' => $mapping ] );
    }

    /** Registra o metabox no editor de ic-campaign */
    public static function register_metabox(): void {
        add_meta_box(
            'pxo_product_acf_linker',
            '&#128279; V&iacute;nculo Produto &harr; Campo ACF',
            [ __CLASS__, 'render_metabox' ],
            'ic-campaign',
            'side',       // ou 'normal' — fica na coluna lateral
            'high'
        );
    }

    /** Renderiza o metabox */
    public static function render_metabox( WP_Post $post ): void {
        $post_id = $post->ID;

        wp_cache_delete( $post_id, 'post_meta' );
        $products_raw = get_post_meta( $post_id, 'products', true );
        $mapping      = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );
        if ( ! is_array( $mapping ) )      $mapping      = [];
        if ( ! is_array( $products_raw ) ) $products_raw = [];

        $products = array_values( $products_raw );

        if ( empty( $products ) ) {
            echo '<p style="color:#999;font-size:12px;">Nenhum produto cadastrado ainda.<br>Adicione produtos no bloco "Products" acima.</p>';
            return;
        }

        $nonce    = wp_create_nonce( 'pxo_acf_mapping' );
        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        ?>
        <style>
        #pxo_product_acf_linker .pxo-link-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        #pxo_product_acf_linker .pxo-link-row:last-child { border-bottom: none; margin-bottom: 0; }
        #pxo_product_acf_linker .pxo-link-name {
            flex: 1;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
        }
        #pxo_product_acf_linker .pxo-link-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #26c6da;
            color: #fff;
            border-radius: 50%;
            font-size: 10px;
            font-weight: bold;
            flex-shrink: 0;
        }
        #pxo_product_acf_linker .pxo-link-select {
            width: 100%;
            height: 28px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 11px;
            padding: 0 4px;
            background: #fff;
            cursor: pointer;
            margin-top: 4px;
        }
        #pxo_product_acf_linker .pxo-link-select:focus { border-color: #0097a7; outline: none; }
        #pxo_product_acf_linker .pxo-link-btn {
            margin-top: 4px;
            width: 100%;
            height: 26px;
            background: #0097a7;
            color: #fff;
            border: none;
            border-radius: 3px;
            font-size: 11px;
            cursor: pointer;
            font-weight: 600;
            transition: background .15s;
        }
        #pxo_product_acf_linker .pxo-link-btn:hover    { background: #00796b; }
        #pxo_product_acf_linker .pxo-link-btn:disabled { background: #b2dfdb; cursor: default; }
        #pxo_product_acf_linker .pxo-link-status { font-size: 10px; margin-top: 2px; display: block; min-height: 14px; }
        #pxo_product_acf_linker .pxo-ok  { color: #388e3c; }
        #pxo_product_acf_linker .pxo-err { color: #c62828; }
        </style>

        <div id="pxo-linker-wrap">
        <?php foreach ( $products as $i => $item ) :
            $position = $i + 1;
            $pid      = absint( $item['product_id'] ?? 0 );
            $qty      = $item['quantity'] ?? '1';
            $name     = '';
            if ( $pid && function_exists( 'wc_get_product' ) ) {
                $p    = wc_get_product( $pid );
                $name = $p ? $p->get_name() : "Produto #{$pid}";
            }
            $saved_slot = (int) ( $mapping[ (string) $position ] ?? 0 );
        ?>
        <div class="pxo-link-row" data-position="<?php echo $position; ?>">
            <div style="flex:1; min-width:0;">
                <div style="display:flex; align-items:center; gap:5px; margin-bottom:3px;">
                    <span class="pxo-link-num"><?php echo $position; ?></span>
                    <span class="pxo-link-name" title="<?php echo esc_attr( $name ); ?>">
                        <?php echo esc_html( mb_strimwidth( $name, 0, 40, '...' ) ); ?>
                        <?php if ( $qty && $qty != '1' ) : ?>
                            <span style="color:#0097a7; font-weight:600;"> &times;<?php echo esc_html( $qty ); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <select class="pxo-link-select" data-position="<?php echo $position; ?>">
                    <option value="0">&#8212; Sem v&iacute;nculo &#8212;</option>
                    <?php for ( $n = 1; $n <= 10; $n++ ) : ?>
                        <option value="<?php echo $n; ?>" <?php selected( $saved_slot, $n ); ?>>
                            Proposta: Informa&ccedil;&otilde;es sobre o Produto <?php echo $n; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <button type="button" class="pxo-link-btn"
                        data-post="<?php echo $post_id; ?>"
                        data-nonce="<?php echo esc_attr( $nonce ); ?>"
                        data-ajax="<?php echo esc_attr( $ajax_url ); ?>"
                        data-position="<?php echo $position; ?>">
                    Salvar v&iacute;nculo
                </button>
                <span class="pxo-link-status"></span>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <script>
        (function($){
            $('#pxo-linker-wrap').on('click', '.pxo-link-btn', function(){
                var $btn  = $(this);
                var pos   = $btn.data('position');
                var $row  = $btn.closest('.pxo-link-row');
                var slot  = parseInt($row.find('.pxo-link-select').val(), 10);
                var $st   = $row.find('.pxo-link-status');

                $btn.prop('disabled', true).text('Salvando...');
                $st.removeClass('pxo-ok pxo-err').text('');

                $.post($btn.data('ajax'), {
                    action:   'pxo_save_acf_mapping',
                    nonce:    $btn.data('nonce'),
                    post_id:  $btn.data('post'),
                    position: pos,
                    slot:     slot
                })
                .done(function(r){
                    $btn.prop('disabled', false).text('Salvar vínculo');
                    if (r && r.success) {
                        $st.addClass('pxo-ok').text('✓ Vinculo salvo!');
                        // Atualiza badge no metabox ACF correspondente
                        pxoUpdateBadge(slot, $row.find('.pxo-link-name').text().trim());
                        setTimeout(function(){ $st.text(''); }, 3000);
                    } else {
                        $st.addClass('pxo-err').text('Erro ao salvar.');
                    }
                })
                .fail(function(xhr, status, error){
                    $btn.prop('disabled', false).text('Salvar v\u00ednculo');
                    var detalhe = 'HTTP ' + xhr.status + ' - ' + (xhr.responseText ? xhr.responseText.substring(0, 120) : error);
                    $st.addClass('pxo-err').text('Erro: ' + detalhe);
                    console.error('[PXO AJAX] falha ao salvar vinculo:', detalhe, xhr);
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /** Injeta badges nos metaboxes ACF e funcao global pxoUpdateBadge */
    public static function inject_badges_js(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'ic-campaign' ) return;
        global $post;
        $post_id = $post->ID ?? 0;
        if ( ! $post_id ) return;

        wp_cache_delete( $post_id, 'post_meta' );
        $products_raw = get_post_meta( $post_id, 'products', true );
        $mapping      = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );
        if ( ! is_array( $mapping ) )      $mapping      = [];
        if ( ! is_array( $products_raw ) ) $products_raw = [];

        // Constroi slot -> nome do produto
        $slot_names = [];
        $products   = array_values( $products_raw );
        foreach ( $products as $i => $item ) {
            $pos  = $i + 1;
            $slot = (int) ( $mapping[ (string) $pos ] ?? 0 );
            if ( $slot < 1 ) continue;
            $pid  = absint( $item['product_id'] ?? 0 );
            $name = '';
            if ( $pid && function_exists( 'wc_get_product' ) ) {
                $p    = wc_get_product( $pid );
                $name = $p ? $p->get_name() : "#{$pid}";
            }
            $qty = $item['quantity'] ?? '1';
            if ( $qty && $qty != '1' ) $name .= " \xc3\x97{$qty}";
            $slot_names[ $slot ] = $name;
        }

        $json = wp_json_encode( $slot_names );
        ?>
        <style>
        .pxo-product-badge {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 10px;
            background: #e8f8fb;
            border: 1px solid #26c6da;
            color: #0097a7;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
            cursor: default;
        }
        .pxo-product-badge--empty {
            border-color: #ddd;
            background: #fafafa;
            color: #bbb;
        }
        </style>
        <script>
        var pxoSlotNames = <?php echo $json; ?>;

        function pxoApplyBadges() {
            jQuery('.postbox').each(function(){
                var $h2 = jQuery(this).find('h2.hndle').first();
                if (!$h2.length) return;
                var m   = $h2.text().trim().match(/[Pp]roduto\s*(\d+)/i);
                if (!m) return;
                var slot = parseInt(m[1], 10);
                $h2.find('.pxo-product-badge').remove();
                var $b = jQuery('<span class="pxo-product-badge"></span>');
                if (pxoSlotNames[slot]) {
                    $b.text(pxoSlotNames[slot]);
                } else {
                    $b.text('Sem produto vinculado').addClass('pxo-product-badge--empty');
                }
                $h2.append($b);
            });
        }

        function pxoUpdateBadge(slot, name) {
            if (slot >= 1) {
                pxoSlotNames[slot] = name;
            }
            pxoApplyBadges();
        }

        jQuery(document).ready(function(){
            pxoApplyBadges();
            jQuery(document).on('click', '.postbox .handlediv, .postbox .hndle', function(){
                setTimeout(pxoApplyBadges, 150);
            });
        });
        </script>
        <?php
    }
}
