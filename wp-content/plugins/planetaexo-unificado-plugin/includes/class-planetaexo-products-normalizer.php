<?php
/**
 * PlanetaExo — Normalizador de Produtos
 *
 * PROBLEMA RAIZ:
 *   O CampaignSaveProducts.php (Cart Link) gera um UUID NOVO a cada save.
 *   Ex: save 1 → "9d2a4e65-..." → save 2 → "ceb5d745-..." → save 3 → "6e784869-..."
 *   Isso faz com que array_values() mude a ordem de exibição e desalinhe
 *   descricao_produto_1, descricao_produto_2... dos produtos reais.
 *
 * SOLUÇÃO:
 *   Após todo save de ic-campaign (prio 9999 = bem depois do Cart Link),
 *   reescrevemos o meta 'products' com chaves ESTÁVEIS: "1","2","3"...
 *   Isso elimina os UUIDs e torna o mapeamento posicional permanentemente correto.
 *
 *   Além disso: ao salvar um campo ACF individual (via botão "Salvar"),
 *   o valor é persistido mesmo que o Update principal seja clicado depois,
 *   porque o template lê por posição fixa e não por UUID.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoProductsNormalizer {

    public static function init(): void {
        // Roda MUITO DEPOIS do Cart Link (CampaignSaveProducts prio ~10) e do ACF (prio 5)
        add_action( 'save_post_ic-campaign', [ __CLASS__, 'normalize_products' ], 9999, 1 );

        // Cobre também o hook genérico save_post (Cart Link pode usar este)
        add_action( 'save_post', [ __CLASS__, 'normalize_products_generic' ], 9999, 1 );

        // Também cobre saves via REST API e chamadas diretas
        add_action( 'acf/save_post', [ __CLASS__, 'normalize_products_acf' ], 9999 );
    }

    /** Wrapper para save_post genérico — filtra por post type */
    public static function normalize_products_generic( int $post_id ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        self::normalize_products( $post_id );
    }

    /**
     * Normaliza chaves do meta 'products' para "1","2","3"...
     * e re-numera os campos ACF descricao_produto_N e data_da_viagem_N
     * caso os produtos tenham mudado de posição.
     */
    public static function normalize_products( int $post_id ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;

        // Lê direto do DB (evita cache stale)
        wp_cache_delete( $post_id, 'post_meta' );
        $products = get_post_meta( $post_id, 'products', true );

        if ( ! is_array( $products ) || empty( $products ) ) return;

        // Verifica se já está normalizado (chaves "1","2","3"...)
        $keys = array_keys( $products );
        $is_normalized = true;
        foreach ( $keys as $i => $key ) {
            if ( (string) $key !== (string) ( $i + 1 ) ) {
                $is_normalized = false;
                break;
            }
        }

        if ( $is_normalized ) return; // nada a fazer

        // Reorganiza: mantém product_id, quantity, price — troca chave UUID → "1","2","3"
        $normalized = [];
        $n = 1;
        foreach ( $products as $product_data ) {
            if ( ! is_array( $product_data ) ) continue;
            $normalized[ (string) $n ] = [
                'product_id' => $product_data['product_id'] ?? '',
                'quantity'   => $product_data['quantity']   ?? '1',
                'price'      => $product_data['price']      ?? '',
            ];
            $n++;
        }

        if ( empty( $normalized ) ) return;

        // Salva com as chaves normalizadas (sem gerar um novo save_post,
        // apenas atualiza o meta diretamente)
        update_post_meta( $post_id, 'products', $normalized );
        clean_post_cache( $post_id );
        wp_cache_delete( $post_id, 'post_meta' );
    }

    /** Wrapper para o hook acf/save_post (recebe $post_id como mixed) */
    public static function normalize_products_acf( $post_id ): void {
        if ( ! is_numeric( $post_id ) ) return;
        // Aguarda 0ms para garantir que o Cart Link já rodou
        self::normalize_products( (int) $post_id );
    }
}
