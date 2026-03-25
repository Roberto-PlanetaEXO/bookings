<?php
/**
 * PlanetaExo — Cart Link Integration
 *
 * Porta e substitui completamente o plugin "Cart Link for WooCommerce".
 * Trata todo o ciclo:
 *
 *  1. TRIGGER (/{slug}/?book=1 → is_404 + ic-campaign)
 *     Esvazia o carrinho, adiciona os produtos da campanha, redireciona para
 *     /checkout/?c=HASH. Idêntico ao TriggerAction original do Cart Link,
 *     mas rodando em priority 1 (antes que o Cart Link rode em priority 5).
 *
 *  2. CHECKOUT FIX (/checkout/?c=HASH)
 *     Roda no hook 'wp' (priority 1, antes de qualquer template_redirect).
 *     Decodifica o ?c=, reconstrói o carrinho e chama save_data() no MESMO
 *     request — sem redirect extra — eliminando o bug de acúmulo causado
 *     pelo die() do Cart Link que impedia o save_data() de rodar.
 *
 *  3. PREÇO CUSTOMIZADO
 *     Hook woocommerce_before_calculate_totals aplica preços personalizados
 *     salvos em cada item de carrinho (meta _pxo_custom_price).
 *
 *  4. AJAX pxo_update_quantities
 *     Atualiza quantidades no post_meta e retorna a URL ?c=HASH para o JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoCartLink {

    const POST_TYPE       = 'ic-campaign';
    const SALT            = 'meuSaltSecreto123';   // mesmo salt do Cart Link original
    const META_PRODUCTS   = 'products';
    const META_CLEAR_CART = 'clear_cart';
    const FIELD_CUSTOM_PRICE = '_pxo_custom_price';
    const FIELD_CAMPAIGN_ID  = '_campaign_id';

    public static function init(): void {
        // ── 1. Trigger: intercepta /{slug}/ (com ou sem ?book=1) → preenche carrinho → /checkout/?c=
        add_action( 'template_redirect', [ __CLASS__, 'trigger_campaign' ], 1 );

        // ── 2. Cookie early: sempre que ?c= está na URL, grava campaign_id (persiste entre requests)
        add_action( 'init', [ __CLASS__, 'maybe_set_campaign_cookie' ], 1 );

        // ── 3. Checkout fix: intercepta /checkout/?c= e reconstrói carrinho no mesmo request
        add_action( 'wp', [ __CLASS__, 'fix_cart_on_checkout' ], 1 );

        // ── 4. Fallback dentro do checkout (caso 'wp' não tenha agido)
        add_action( 'woocommerce_checkout_init', [ __CLASS__, 'fix_cart_on_checkout_init' ], 1 );

        // ── 5. Preços customizados por item de carrinho
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'apply_custom_prices' ], 10 );

        // ── 6. AJAX — atualiza quantidades e retorna URL ?c=
        add_action( 'wp_ajax_nopriv_pxo_update_quantities', [ __CLASS__, 'ajax_update_quantities' ] );
        add_action( 'wp_ajax_pxo_update_quantities',        [ __CLASS__, 'ajax_update_quantities' ] );

        // Alias para compatibilidade com o JS que use 'pxo_book_now'
        add_action( 'wp_ajax_nopriv_pxo_book_now', [ __CLASS__, 'ajax_update_quantities' ] );
        add_action( 'wp_ajax_pxo_book_now',        [ __CLASS__, 'ajax_update_quantities' ] );
    }

    /* ═══════════════════════════════════════════════════════
     * 1. TRIGGER CAMPAIGN
     *    Replica o TriggerAction do Cart Link:
     *    - Só age em 404 (ic-campaign tem rewrite=false)
     *    - Resolve o slug → post id
     *    - Esvazia carrinho, adiciona produtos com meta _campaign_id
     *    - Redireciona para checkout?c=HASH + die()
     *    Roda em priority 1, antes do Cart Link (priority 5).
     *    Se este plugin estiver ativo, o Cart Link nunca chega a rodar.
     * ═══════════════════════════════════════════════════════ */
    public static function trigger_campaign(): void {
        global $wp;

        if ( ! is_404() ) {
            return;
        }

        // Só age quando o botão "Book Now" é clicado (?book=1).
        // Sem esse parâmetro, deixa pxo_intercept_proposta (tema) renderizar a proposta.
        if ( ! isset( $_GET['book'] ) || $_GET['book'] !== '1' ) {
            return;
        }

        $slug          = trim( $wp->request, '/' );
        $campaign_post = get_page_by_path( $slug, OBJECT, self::POST_TYPE );

        if ( ! $campaign_post ) {
            return;
        }

        if ( get_post_status( $campaign_post->ID ) !== 'publish' ) {
            return;
        }

        nocache_headers();

        $campaign_id = $campaign_post->ID;
        $products    = self::get_products_data( $campaign_id );

        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        $cart       = WC()->cart;
        $clear_all  = ( get_post_meta( $campaign_id, self::META_CLEAR_CART, true ) !== 'no' );

        // Esvazia: ou tudo, ou só os itens desta campanha
        if ( $clear_all ) {
            $cart->empty_cart();
        } else {
            self::remove_campaign_items( $cart, $campaign_id );
        }

        // Adiciona produtos com meta de rastreio e preço customizado
        foreach ( $products as $product_data ) {
            $pid   = absint( $product_data['product_id'] ?? 0 );
            $qty   = (float) str_replace( ',', '.', $product_data['quantity'] ?? 1 );
            $price = isset( $product_data['price'] ) && $product_data['price'] !== ''
                        ? (float) str_replace( ',', '.', $product_data['price'] )
                        : null;

            if ( ! $pid || ! $qty ) continue;

            $wc_product = wc_get_product( $pid );
            if ( ! $wc_product instanceof WC_Product ) continue;
            if ( ! $wc_product->is_purchasable() )      continue;

            $cart_meta = [ self::FIELD_CAMPAIGN_ID => $campaign_id ];
            if ( $price !== null ) {
                $cart_meta[ self::FIELD_CUSTOM_PRICE ] = $price;
            }

            if ( $wc_product->get_parent_id() ) {
                $cart->add_to_cart( $wc_product->get_parent_id(), $qty, $pid, [], $cart_meta );
            } else {
                $cart->add_to_cart( $pid, $qty, 0, [], $cart_meta );
            }
        }

        // Gera ?c= e redireciona para checkout (exatamente como o Cart Link fazia)
        $c_param      = self::encode_campaign_id( $campaign_id );
        $redirect_url = add_query_arg( 'c', $c_param, wc_get_checkout_url() );

        wp_safe_redirect( $redirect_url );
        die();
        // NOTA: o die() aqui é intencional e idêntico ao Cart Link original.
        // O save_data() será feito pelo hook 'wp' (fix_cart_on_checkout) no
        // próximo request quando o browser chegar em /checkout/?c=HASH.
    }

    /**
     * Grava cookie pxo_cid sempre que ?c= está na URL (qualquer página).
     * Garante que o campaign_id esteja disponível quando o pedido for criado,
     * mesmo se sessão falhar ou o usuário chegar por caminho inesperado.
     */
    public static function maybe_set_campaign_cookie(): void {
        $c = sanitize_text_field( $_GET['c'] ?? '' );
        if ( ! $c ) return;
        $campaign_id = self::decode_c_param( $c );
        if ( ! $campaign_id ) return;
        if ( ! headers_sent() ) {
            setcookie( self::COOKIE_CAMPAIGN, (string) $campaign_id, time() + self::COOKIE_EXPIRY, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    }

    /* ═══════════════════════════════════════════════════════
     * 3. CHECKOUT FIX — hook 'wp' priority 1
     *    Quando o browser chega em /checkout/?c=HASH:
     *    - Decodifica o campaign_id
     *    - Esvazia o carrinho e adiciona os produtos NESTE request
     *    - Chama save_data() explicitamente (o die() anterior impediu isso)
     *    - NÃO redireciona → o checkout renderiza com a URL ?c= intacta
     *    Isso elimina o bug de acúmulo causado pelo Redis/sessão antiga.
     * ═══════════════════════════════════════════════════════ */
    public static function fix_cart_on_checkout(): void {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        $c = sanitize_text_field( $_GET['c'] ?? '' );
        if ( ! $c ) return;

        $campaign_id = self::decode_c_param( $c );
        if ( ! $campaign_id ) return;

        nocache_headers();
        self::rebuild_cart( $campaign_id );
        self::store_campaign_id_in_session( $campaign_id );
        // Sem redirect → checkout renderiza aqui mesmo com URL /checkout/?c=HASH ✅
    }

    /* ═══════════════════════════════════════════════════════
     * 3. FALLBACK no woocommerce_checkout_init
     * ═══════════════════════════════════════════════════════ */
    public static function fix_cart_on_checkout_init(): void {
        $c = sanitize_text_field( $_GET['c'] ?? '' );
        if ( ! $c ) return;

        $campaign_id = self::decode_c_param( $c );
        if ( ! $campaign_id ) return;

        nocache_headers();
        self::rebuild_cart( $campaign_id );
        self::store_campaign_id_in_session( $campaign_id );
    }

    const COOKIE_CAMPAIGN = 'pxo_cid';
    const COOKIE_EXPIRY   = 3600; // 1 hora

    /**
     * Grava campaign_id na sessão e em cookie para PlanetaExoOrderMeta usar ao criar o pedido.
     * Cookie é fallback quando sessão não persiste (ex.: Pressable, Redis, AJAX).
     */
    private static function store_campaign_id_in_session( int $campaign_id ): void {
        if ( session_id() || @session_start() ) {
            $_SESSION['pxo_campaign_id'] = $campaign_id;
            $_SESSION['c_value']        = self::encode_campaign_id( $campaign_id );
        }
        if ( ! headers_sent() ) {
            setcookie( self::COOKIE_CAMPAIGN, (string) $campaign_id, time() + self::COOKIE_EXPIRY, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
    }

    /* ═══════════════════════════════════════════════════════
     * 4. PREÇOS CUSTOMIZADOS
     * ═══════════════════════════════════════════════════════ */
    public static function apply_custom_prices( WC_Cart $cart ): void {
        if ( doing_action( 'woocommerce_before_calculate_totals' ) && ! did_action( 'woocommerce_before_calculate_totals' ) ) {
            return; // evita loop
        }
        foreach ( $cart->get_cart_contents() as $item ) {
            if ( isset( $item[ self::FIELD_CUSTOM_PRICE ] ) ) {
                $item['data']->set_price( (float) $item[ self::FIELD_CUSTOM_PRICE ] );
            }
        }
    }

    /* ═══════════════════════════════════════════════════════
     * 5. AJAX — atualiza quantidades → retorna URL ?c=
     * ═══════════════════════════════════════════════════════ */
    public static function ajax_update_quantities(): void {
        if ( ! check_ajax_referer( 'pxo_proposta', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
        }

        $post_id    = absint( $_POST['post_id'] ?? 0 );
        $quantities = $_POST['quantities'] ?? [];

        if ( ! $post_id || get_post_type( $post_id ) !== self::POST_TYPE ) {
            wp_send_json_error( [ 'message' => 'Proposta não encontrada.' ], 404 );
        }

        // Atualiza quantidades no post_meta se foram enviadas
        if ( is_array( $quantities ) && ! empty( $quantities ) ) {
            $products = get_post_meta( $post_id, self::META_PRODUCTS, true );
            if ( is_array( $products ) ) {
                // Normaliza para índices numéricos (o array pode estar
                // indexado por UUIDs quando salvo pelo Cart Link original).
                $products = array_values( $products );

                foreach ( $products as $i => &$product_data ) {
                    // JS envia quantities[1], quantities[2]... (1-based)
                    $key = (string) ( $i + 1 );
                    if ( array_key_exists( $key, $quantities ) ) {
                        $new_qty = intval( $quantities[ $key ] );
                        $product_data['quantity'] = max( 0, $new_qty );
                    }
                }
                unset( $product_data );

                // Remove itens com qty = 0
                $products = array_values( array_filter( $products, fn( $p ) => (int) ( $p['quantity'] ?? 0 ) > 0 ) );

                // Salva no DB e invalida caches (Redis persistente incluso)
                update_post_meta( $post_id, self::META_PRODUCTS, $products );
                clean_post_cache( $post_id );
                wp_cache_delete( $post_id, 'post_meta' );
            }
        }

        $c_param = self::encode_campaign_id( $post_id );
        wp_send_json_success( [
            'redirect' => add_query_arg( 'c', $c_param, wc_get_checkout_url() ),
        ] );
    }

    /* ═══════════════════════════════════════════════════════
     * HELPERS PRIVADOS
     * ═══════════════════════════════════════════════════════ */

    /**
     * Gera o parâmetro ?c= para um campaign_id.
     * Formato: crc32b(salt + id) [8 chars hex] + dechex(id)
     */
    public static function encode_campaign_id( int $campaign_id ): string {
        $hash = hash( 'crc32b', self::SALT . $campaign_id );
        return $hash . dechex( $campaign_id );
    }

    /**
     * Decodifica um parâmetro ?c= e retorna o campaign_id, ou 0 se inválido.
     */
    public static function decode_c_param( string $c ): int {
        if ( strlen( $c ) < 9 ) return 0;

        $hash_received = substr( $c, 0, 8 );
        $campaign_id   = (int) hexdec( substr( $c, 8 ) );

        if ( ! $campaign_id ) return 0;

        $expected = hash( 'crc32b', self::SALT . $campaign_id );
        if ( ! hash_equals( $expected, $hash_received ) ) return 0;

        if ( get_post_type( $campaign_id ) !== self::POST_TYPE ) return 0;

        return $campaign_id;
    }

    /**
     * Lê a lista de produtos do post_meta.
     */
    private static function get_products_data( int $campaign_id ): array {
        $products = get_post_meta( $campaign_id, self::META_PRODUCTS, true );
        return is_array( $products ) ? $products : [];
    }

    /**
     * Remove do carrinho apenas os itens que pertencem a esta campanha.
     */
    private static function remove_campaign_items( WC_Cart $cart, int $campaign_id ): void {
        foreach ( $cart->get_cart_contents() as $key => $item ) {
            if ( isset( $item[ self::FIELD_CAMPAIGN_ID ] ) && (int) $item[ self::FIELD_CAMPAIGN_ID ] === $campaign_id ) {
                $cart->remove_cart_item( $key );
            }
        }
    }

    /**
     * Reconstrói o carrinho a partir do post_meta do campaign_id.
     * Usado pelo fix_cart_on_checkout — sem redirect, apenas atualiza em memória.
     */
    private static function rebuild_cart( int $campaign_id ): void {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

        // Força leitura fresca do DB ignorando object cache (Redis persistente).
        // Necessário porque o AJAX que atualizou as quantidades rodou em outro
        // request e o cache pode ser stale neste novo request de checkout.
        wp_cache_delete( $campaign_id, 'post_meta' );
        clean_post_cache( $campaign_id );

        $products = self::get_products_data( $campaign_id );
        if ( empty( $products ) ) return;

        $cart = WC()->cart;

        // Esvazia completamente (comportamento padrão do Cart Link quando clear_cart = yes)
        $cart->empty_cart( true );

        foreach ( $products as $product_data ) {
            $pid   = absint( $product_data['product_id'] ?? 0 );
            $qty   = (float) str_replace( ',', '.', $product_data['quantity'] ?? 1 );
            $price = isset( $product_data['price'] ) && $product_data['price'] !== ''
                        ? (float) str_replace( ',', '.', $product_data['price'] )
                        : null;

            if ( ! $pid || ! $qty ) continue;

            $wc_product = wc_get_product( $pid );
            if ( ! $wc_product instanceof WC_Product ) continue;

            $cart_meta = [ self::FIELD_CAMPAIGN_ID => $campaign_id ];
            if ( $price !== null ) {
                $cart_meta[ self::FIELD_CUSTOM_PRICE ] = $price;
            }

            if ( $wc_product->get_parent_id() ) {
                $cart->add_to_cart( $wc_product->get_parent_id(), $qty, $pid, [], $cart_meta );
            } else {
                $cart->add_to_cart( $pid, $qty, 0, [], $cart_meta );
            }
        }

        // Salva sessão neste mesmo request (resolve o bug do die() no Cart Link)
        WC()->session->save_data();

        // Invalida Redis object cache para a sessão deste usuário
        $customer_id = WC()->session->get_customer_id();
        wp_cache_delete( 'customer_' . $customer_id, 'woocommerce_sessions' );
        wp_cache_delete( $customer_id, 'woocommerce_sessions' );
    }
}
