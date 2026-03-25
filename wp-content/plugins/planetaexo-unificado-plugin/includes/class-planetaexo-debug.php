<?php
/**
 * PlanetaExo — Diagnóstico de Quantidade/Preço no Checkout
 *
 * Registra cada etapa do fluxo Book Now → Checkout em um arquivo de log
 * e exibe os dados em uma página de admin acessível em:
 *   WP Admin → Planeta Exo → 🔍 Debug Checkout
 *
 * ATIVAR: basta ter este arquivo incluso e PlanetaExoDebug::init() chamado.
 * DESATIVAR: remova o require_once e PlanetaExoDebug::init() do plugin principal.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoDebug {

    /** Arquivo de log — gerado na pasta do plugin, fora do webroot ideal */
    const LOG_FILE  = 'pxo-checkout-debug.log';
    const MAX_LINES = 500; // linhas máximas no log (rotação simples)
    const NONCE     = 'pxo_debug_clear';

    public static function init(): void {
        // ── 1. Intercepta AJAX pxo_update_quantities (antes e depois) ──────────
        add_action( 'wp_ajax_nopriv_pxo_update_quantities', [ __CLASS__, 'before_ajax' ], 0 );
        add_action( 'wp_ajax_pxo_update_quantities',        [ __CLASS__, 'before_ajax' ], 0 );
        add_action( 'wp_ajax_nopriv_pxo_book_now',          [ __CLASS__, 'before_ajax' ], 0 );
        add_action( 'wp_ajax_pxo_book_now',                 [ __CLASS__, 'before_ajax' ], 0 );

        // ── 2. Intercepta o rebuild do carrinho no checkout ──────────────────
        add_action( 'wp',                      [ __CLASS__, 'on_checkout_wp'   ], 0 );
        add_action( 'woocommerce_checkout_init',[ __CLASS__, 'on_checkout_init'], 0 );

        // ── 3. Loga o que entrou no carrinho (após rebuild) ──────────────────
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'log_cart_contents' ], 999 );

        // ── 4. Página de admin ───────────────────────────────────────────────
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
    }

    /* ─────────────────────────────────────────────────────────
     * HOOKS DE DIAGNÓSTICO
     * ───────────────────────────────────────────────────────── */

    /** Antes do AJAX processar: loga o que chegou + o que está no DB agora */
    public static function before_ajax(): void {
        $post_id    = absint( $_POST['post_id']    ?? 0 );
        $quantities = $_POST['quantities'] ?? [];

        self::log( '═══════════════════════════════════════' );
        self::log( '📤 AJAX pxo_update_quantities RECEBIDO' );
        self::log( '   post_id:    ' . $post_id );
        self::log( '   quantities: ' . wp_json_encode( $quantities ) );

        if ( $post_id ) {
            $before = get_post_meta( $post_id, 'products', true );
            self::log( '   post_meta ANTES (products): ' . wp_json_encode( $before ) );
        }

        // Registra callback para rodar DEPOIS do handler real (prio 10) confirmar o save
        add_action( current_action(), [ __CLASS__, 'after_ajax' ], 99 );
    }

    /** Após o AJAX processar: loga o que ficou salvo no DB */
    public static function after_ajax(): void {
        $post_id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $post_id ) return;

        // Força leitura direta do DB (ignora cache local)
        wp_cache_delete( $post_id, 'post_meta' );
        $after = get_post_meta( $post_id, 'products', true );

        self::log( '   post_meta DEPOIS (products): ' . wp_json_encode( $after ) );
        self::log( '   object cache key presente: ' . ( wp_cache_get( $post_id, 'post_meta' ) !== false ? 'SIM' : 'NÃO' ) );
    }

    /** No hook 'wp' do checkout: loga o ?c= e o que está no DB naquele momento */
    public static function on_checkout_wp(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;

        $c = sanitize_text_field( $_GET['c'] ?? '' );
        if ( ! $c ) return;

        self::log( '═══════════════════════════════════════' );
        self::log( '🛍  CHECKOUT REQUEST (hook wp, prio 0)' );
        self::log( '   ?c= param: ' . $c );

        $campaign_id = self::decode_c( $c );
        self::log( '   campaign_id decodificado: ' . $campaign_id );

        if ( $campaign_id ) {
            // Lê SEM cache (wp_cache_delete antes)
            wp_cache_delete( $campaign_id, 'post_meta' );
            $products_fresh = get_post_meta( $campaign_id, 'products', true );
            self::log( '   products (leitura DIRETA DB): ' . wp_json_encode( $products_fresh ) );

            // Lê COM cache (o que o cache tem agora)
            $products_cached = wp_cache_get( $campaign_id, 'post_meta' );
            self::log( '   products (do object cache):   ' . wp_json_encode( $products_cached ) );
        }
    }

    /** No hook woocommerce_checkout_init: mesmo diagnóstico como fallback */
    public static function on_checkout_init(): void {
        $c = sanitize_text_field( $_GET['c'] ?? '' );
        if ( ! $c ) return;

        self::log( '🛍  CHECKOUT (hook checkout_init, prio 0) — ?c= ' . $c );
        $campaign_id = self::decode_c( $c );
        if ( $campaign_id ) {
            wp_cache_delete( $campaign_id, 'post_meta' );
            $products = get_post_meta( $campaign_id, 'products', true );
            self::log( '   products (checkout_init, DB direto): ' . wp_json_encode( $products ) );
        }
    }

    /** Loga o conteúdo do carrinho após o rebuild */
    public static function log_cart_contents( $cart ): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
        if ( ! isset( $_GET['c'] ) ) return;

        self::log( '🛒 CARRINHO após rebuild (woocommerce_before_calculate_totals, prio 999):' );
        $items = $cart->get_cart_contents();
        if ( empty( $items ) ) {
            self::log( '   ⚠️  CARRINHO VAZIO!' );
            return;
        }
        foreach ( $items as $key => $item ) {
            $pid      = $item['product_id']   ?? 0;
            $qty      = $item['quantity']      ?? 0;
            $price    = isset( $item['data'] ) ? $item['data']->get_price() : 'n/a';
            $custom_p = $item['_pxo_custom_price'] ?? 'não definido';
            $camp_id  = $item['_campaign_id']      ?? 'não definido';
            self::log( sprintf(
                '   item %s → pid=%d qty=%s price_wc=%s custom_price=%s campaign_id=%s',
                $key, $pid, $qty, $price, $custom_p, $camp_id
            ) );
        }
    }

    /* ─────────────────────────────────────────────────────────
     * PÁGINA DE ADMIN
     * ───────────────────────────────────────────────────────── */

    public static function register_menu(): void {
        add_submenu_page(
            'planeta-exo',
            '🔍 Debug Checkout',
            '🔍 Debug Checkout',
            'manage_options',
            'pxo-debug-checkout',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        // Botão de limpar log
        if ( isset( $_POST['pxo_clear_log'] ) && check_admin_referer( self::NONCE ) ) {
            file_put_contents( self::log_path(), '' );
            echo '<div class="notice notice-success"><p>Log apagado.</p></div>';
        }

        $log_path = self::log_path();
        $content  = file_exists( $log_path ) ? file_get_contents( $log_path ) : '';

        echo '<div class="wrap">';
        echo '<h1>🔍 Debug Checkout — Fluxo de Quantidades</h1>';
        echo '<p><strong>Instruções:</strong> faça o fluxo completo no site (abra proposta → mude qty → clique Book Now → veja o checkout). Depois atualize esta página.</p>';
        echo '<p style="color:#666;font-size:12px">Log em: <code>' . esc_html( $log_path ) . '</code></p>';

        // Botão limpar
        echo '<form method="post" style="margin-bottom:12px">';
        wp_nonce_field( self::NONCE );
        echo '<button name="pxo_clear_log" value="1" class="button button-secondary">🗑 Apagar log</button>';
        echo '</form>';

        // Exibe log
        if ( $content ) {
            $lines = array_reverse( array_filter( explode( "\n", $content ) ) );
            echo '<textarea readonly style="width:100%;height:600px;font-family:monospace;font-size:12px;background:#1e1e1e;color:#9cdcfe;padding:12px;border:none">';
            echo esc_textarea( implode( "\n", $lines ) );
            echo '</textarea>';
        } else {
            echo '<p style="color:#999">Nenhum dado no log ainda. Faça o fluxo Book Now → Checkout para gerar registros.</p>';
        }

        echo '</div>';
    }

    /* ─────────────────────────────────────────────────────────
     * HELPERS INTERNOS
     * ───────────────────────────────────────────────────────── */

    private static function log( string $message ): void {
        $path = self::log_path();
        $line = '[' . date( 'H:i:s' ) . '] ' . $message . "\n";

        // Rotação simples: se passou de MAX_LINES, mantém só as últimas metade
        if ( file_exists( $path ) ) {
            $lines = file( $path );
            if ( count( $lines ) > self::MAX_LINES ) {
                $lines = array_slice( $lines, - (int)( self::MAX_LINES / 2 ) );
                file_put_contents( $path, implode( '', $lines ) );
            }
        }

        file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
    }

    private static function log_path(): string {
        return plugin_dir_path( dirname( __FILE__ ) ) . self::LOG_FILE;
    }

    /**
     * Replica a decodificação do ?c= sem depender da classe principal.
     */
    private static function decode_c( string $c ): int {
        if ( strlen( $c ) < 9 ) return 0;
        $hash_received = substr( $c, 0, 8 );
        $campaign_id   = (int) hexdec( substr( $c, 8 ) );
        if ( ! $campaign_id ) return 0;
        $expected = hash( 'crc32b', 'meuSaltSecreto123' . $campaign_id );
        if ( ! hash_equals( $expected, $hash_received ) ) return 0;
        return $campaign_id;
    }
}
