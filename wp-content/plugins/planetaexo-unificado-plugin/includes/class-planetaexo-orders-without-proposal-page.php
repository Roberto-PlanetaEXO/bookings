<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoOrdersWithoutProposalPage {
    public static function render() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('Sem permissão.', 'planetaexo-unificado'));
        }

        if (!function_exists('wc_get_orders')) {
            echo '<div class="wrap"><h1>Pedidos sem Proposta</h1><p>WooCommerce não está ativo.</p></div>';
            return;
        }

        self::handle_updates();

        $orders_final = self::get_sorted_orders();

        echo '<div class="wrap">';
        echo '<h1>Pedidos sem o ID da Proposta gravado</h1>';

        if (empty($orders_final)) {
            echo '<p>Nenhum pedido encontrado.</p>';
            echo '</div>';
            return;
        }

        echo '<form method="post">';
        wp_nonce_field('planetaexo_cartlink_update');
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>ID</th><th>Cliente</th><th>Data</th><th>Status</th><th>ID da Proposta</th></tr></thead>';
        echo '<tbody>';

        foreach ($orders_final as $order) {
            $order_id = $order->get_id();
            $customer_name = $order->get_formatted_billing_full_name();
            $date = $order->get_date_created() ? $order->get_date_created()->date('d/m/Y H:i') : '';
            $status = wc_get_order_status_name($order->get_status());
            $id_cartlink = $order->get_meta('_id_cartlink');

            echo '<tr>';
            echo '<td>#' . esc_html((string) $order_id) . '</td>';
            echo '<td>' . esc_html($customer_name) . '</td>';
            echo '<td>' . esc_html($date) . '</td>';
            echo '<td>' . esc_html($status) . '</td>';

            if ((int) $id_cartlink > 0) {
                $edit_url = admin_url('post.php?post=' . absint($id_cartlink) . '&action=edit&classic-editor');
                echo '<td><a href="' . esc_url($edit_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $id_cartlink) . '</a></td>';
            } else {
                echo '<td><input type="text" name="cartlink_updates[' . esc_attr((string) $order_id) . ']" value="' . esc_attr((string) $id_cartlink) . '" /> <button type="submit" class="button button-primary">Salvar ID da Proposta</button></td>';
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</form>';
        echo '</div>';
    }

    private static function handle_updates() {
        if (!isset($_POST['cartlink_updates'], $_POST['_wpnonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        if (!wp_verify_nonce($nonce, 'planetaexo_cartlink_update')) {
            return;
        }

        $updates = (array) $_POST['cartlink_updates'];
        foreach ($updates as $order_id => $cartlink_value) {
            update_post_meta(absint($order_id), '_id_cartlink', sanitize_text_field(wp_unslash($cartlink_value)));
        }

        echo '<div class="updated notice"><p>IDs de proposta atualizados com sucesso.</p></div>';
    }

    private static function get_sorted_orders() {
        $args = [
            'limit' => -1,
            'type' => 'shop_order',
            'status' => ['processing', 'completed', 'on-hold', 'pending'],
            'date_created' => '>' . (new DateTime('-15 days'))->format('Y-m-d H:i:s'),
            'meta_query' => [],
        ];

        $orders = wc_get_orders($args);
        $orders_sem_cartlink = [];
        $orders_com_cartlink = [];

        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                $order = wc_get_order($order);
            }

            if ($order instanceof WC_Order_Refund) {
                continue;
            }

            $cartlink = $order->get_meta('_id_cartlink');
            if (empty($cartlink)) {
                $orders_sem_cartlink[] = $order;
            } else {
                $orders_com_cartlink[] = $order;
            }
        }

        usort($orders_sem_cartlink, fn($a, $b) => $b->get_id() - $a->get_id());
        usort($orders_com_cartlink, fn($a, $b) => $b->get_id() - $a->get_id());

        return array_merge($orders_sem_cartlink, $orders_com_cartlink);
    }
}
