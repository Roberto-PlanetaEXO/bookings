<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoOrderMeta {
    const FIELD_CAMPAIGN_ID = '_campaign_id';
    const META_ID_CARTLINK  = '_id_cartlink';

    /**
     * Copia _campaign_id do item do carrinho para o item do pedido.
     * Garante que o campaign_id fique persistido nos itens (útil para fallbacks).
     */
    public static function copy_campaign_id_to_order_item( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values[ self::FIELD_CAMPAIGN_ID ] ) ) {
            $item->add_meta_data( self::FIELD_CAMPAIGN_ID, (int) $values[ self::FIELD_CAMPAIGN_ID ], true );
        }
    }

    /**
     * Obtém campaign_id e grava em _id_cartlink no pedido.
     * Ordem de fallback: SESSION → carrinho → itens do pedido.
     */
    public static function add_id_cartlink_to_order( $order, $data ) {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        // 1) Cookie — mais confiável que sessão em hosts com Redis/Pressable (persiste entre requests)
        $campaign_id = null;
        if ( ! empty( $_COOKIE['pxo_cid'] ) && is_numeric( $_COOKIE['pxo_cid'] ) ) {
            $campaign_id = (int) $_COOKIE['pxo_cid'];
        }
        // 2) Sessão — definida quando usuário chega em /checkout/?c=HASH (PlanetaExoCartLink)
        if ( ! $campaign_id && ( session_id() || @session_start() ) ) {
            if ( ! empty( $_SESSION['pxo_campaign_id'] ) ) {
                $campaign_id = (int) $_SESSION['pxo_campaign_id'];
            } elseif ( ! empty( $_SESSION['c_value'] ) ) {
                $hash_com_id = (string) $_SESSION['c_value'];
                $id_hex      = substr( $hash_com_id, 8 );
                if ( $id_hex !== false && ctype_xdigit( $id_hex ) ) {
                    $campaign_id = (int) hexdec( $id_hex );
                }
            }
        }
        // 3) Itens do pedido (já têm _campaign_id via copy_campaign_id_to_order_item)
        if ( ! $campaign_id ) {
            $campaign_id = self::get_campaign_id_from_order( $order );
        }
        // 4) Carrinho (fallback se sessão não persistiu)
        if ( ! $campaign_id && WC()->cart ) {
            $campaign_id = self::get_campaign_id_from_cart();
        }

        if ( $campaign_id > 0 ) {
            $order->update_meta_data( self::META_ID_CARTLINK, $campaign_id );
            // Remove cookie após uso (evita reutilização indevida)
            if ( ! headers_sent() && ! empty( $_COOKIE['pxo_cid'] ) ) {
                setcookie( 'pxo_cid', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
            }
        }
    }

    /**
     * Fallback final: se _id_cartlink ainda vazio após order_created, obtém dos itens.
     * Útil quando o carrinho já foi esvaziado ou sessão não persistiu.
     */
    public static function ensure_id_cartlink_after_order_created( $order ) {
        if ( ! $order || ! ( $order instanceof \WC_Order ) ) {
            return;
        }
        if ( $order->get_meta( self::META_ID_CARTLINK ) ) {
            return;
        }
        $campaign_id = self::get_campaign_id_from_order( $order );
        if ( $campaign_id > 0 ) {
            $order->update_meta_data( self::META_ID_CARTLINK, $campaign_id );
            $order->save();
        }
    }

    private static function get_campaign_id_from_cart() {
        foreach ( WC()->cart->get_cart_contents() as $item ) {
            if ( ! empty( $item[ self::FIELD_CAMPAIGN_ID ] ) ) {
                return (int) $item[ self::FIELD_CAMPAIGN_ID ];
            }
        }
        return null;
    }

    private static function get_campaign_id_from_order( $order ) {
        foreach ( $order->get_items() as $item ) {
            if ( is_callable( [ $item, 'get_meta' ] ) ) {
                $val = $item->get_meta( self::FIELD_CAMPAIGN_ID );
                if ( ! empty( $val ) ) {
                    return (int) $val;
                }
            }
        }
        return null;
    }
}
