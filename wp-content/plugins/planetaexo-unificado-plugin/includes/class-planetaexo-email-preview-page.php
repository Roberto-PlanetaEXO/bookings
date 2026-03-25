<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoEmailPreviewPage {
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'planetaexo-unificado'));
        }

        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $email_type = isset($_GET['email_type']) ? sanitize_text_field(wp_unslash($_GET['email_type'])) : '';
        $email_options = self::get_email_options();

        echo '<div class="wrap">';
        echo '<h1>Visualizar E-mails WooCommerce</h1>';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="visualizar-emails-woocommerce">';
        echo '<label for="order_id">ID do Pedido:</label> ';
        echo '<input type="number" name="order_id" id="order_id" value="' . esc_attr((string) $order_id) . '" required> ';
        echo '<label for="email_type">Tipo de E-mail:</label> ';
        echo '<select name="email_type" id="email_type" required>';

        foreach ($email_options as $key => $item) {
            $selected = selected($email_type, $key, false);
            echo '<option value="' . esc_attr($key) . '" ' . $selected . '>' . esc_html($item[1]) . '</option>';
        }

        echo '</select> ';
        submit_button('Visualizar E-mail', 'primary', '', false);
        echo '</form>';

        if ($order_id > 0 && $email_type !== '') {
            echo '<h2>Visualização do E-mail para o Pedido #' . esc_html((string) $order_id) . '</h2>';
            echo '<div style="border:1px solid #ddd;padding:20px;margin-top:20px;">';
            echo self::build_email_preview($order_id, $email_type, $email_options);
            echo '</div>';
        }

        echo '</div>';
    }

    private static function get_email_options() {
        return [
            'wc_email_new_order' => ['WC_Email_New_Order', 'Novo Pedido'],
            'WC_Email_Customer_Invoice' => ['WC_Email_Customer_Invoice', 'Customer Invoice'],
            'wc_email_cancelled_order' => ['WC_Email_Cancelled_Order', 'Pedido Cancelado'],
            'wc_email_failed_order' => ['WC_Email_Failed_Order', 'Pedido Falhou'],
            'wc_email_customer_failed_order' => ['WC_Email_Customer_Failed_Order', 'Pedido Falhou (Cliente)'],
            'wc_email_customer_completed_order' => ['WC_Email_Customer_Completed_Order', 'Pedido Concluído'],
            'wc_email_customer_new_account' => ['WC_Email_Customer_New_Account', 'Nova Conta'],
            'wc_email_customer_processing_order' => ['WC_Email_Customer_Processing_Order', 'Pedido em Processamento'],
        ];
    }

    private static function build_email_preview($order_id, $email_type, $email_options) {
        if (!function_exists('wc_get_order') || !function_exists('WC')) {
            return 'WooCommerce não está ativo.';
        }

        if (!isset($email_options[$email_type])) {
            return 'Tipo de e-mail inválido.';
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return 'Pedido não encontrado.';
        }

        $email_class = $email_options[$email_type][0];
        $emails = WC()->mailer()->get_emails();

        if (empty($emails[$email_class])) {
            return 'Classe de e-mail não disponível.';
        }

        $email = $emails[$email_class];
        $email->object = $order;
        $email->recipient = $order->get_billing_email();

        $content = $email->get_content();
        return apply_filters('woocommerce_mail_content', $email->style_inline($content));
    }
}
