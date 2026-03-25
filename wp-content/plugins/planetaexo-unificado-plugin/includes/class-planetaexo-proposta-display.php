<?php
/**
 * Class PlanetaExoPropostaDisplay
 * 
 * Registra e gerencia a exibição de propostas no frontend
 * - Registra template customizado para ic-campaign
 * - Cria shortcode para exibir proposta em qualquer lugar
 * - Gerencia dados do ACF
 */

class PlanetaExoPropostaDisplay {

    public function __construct() {
        // Registra o template quando tema carrega
        add_filter('single_template', array($this, 'load_custom_template'));
        
        // Cria shortcode para exibir proposta
        add_shortcode('proposta', array($this, 'shortcode_proposta'));
        
        // Enqueue estilos e scripts customizados
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Action para antes de exibir a proposta
        add_action('planetaexo_before_proposta_display', array($this, 'validate_fields'));
    }

    /**
     * Carrega template customizado para propostas (ic-campaign)
     */
    public function load_custom_template($template) {
        global $post;

        if (!$post) {
            return $template;
        }

        // Verifica se é um post do tipo ic-campaign
        if ($post->post_type === 'ic-campaign') {
            $plugin_template = plugin_dir_path(dirname(__FILE__)) . 'templates/proposta-display.php';

            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Cria shortcode para exibir proposta
     * Uso: [proposta id="123"] ou [proposta] (pega post atual)
     */
    public function shortcode_proposta($atts) {
        $atts = shortcode_atts(array(
            'id' => null
        ), $atts);

        // Se não especificou ID, usa o post atual
        if (!$atts['id']) {
            global $post;
            $atts['id'] = $post->ID;
        }

        // Verifica se post existe e é do tipo correto
        $post_obj = get_post($atts['id']);
        if (!$post_obj || $post_obj->post_type !== 'ic-campaign') {
            return '<p style="color: red;">Proposta não encontrada ou tipo inválido.</p>';
        }

        // Captura o output do template
        ob_start();
        
        // Temporariamente muda o post global
        global $post;
        $original_post = $post;
        $post = $post_obj;
        setup_postdata($post);

        // Inclui o template
        include plugin_dir_path(dirname(__FILE__)) . 'templates/proposta-display.php';

        // Restaura post original
        wp_reset_postdata();
        $post = $original_post;

        return ob_get_clean();
    }

    /**
     * Enqueue estilos e scripts
     */
    public function enqueue_assets() {
        global $post;

        if (!$post || $post->post_type !== 'ic-campaign') {
            return;
        }

        // CSS inline já contém os estilos no template
        // Aqui você pode adicionar JavaScript extra se necessário

        wp_enqueue_script(
            'planetaexo-proposta',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/proposta.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Passa dados para JavaScript
        wp_localize_script('planetaexo-proposta', 'PropostaData', array(
            'postId' => $post->ID,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('proposta_nonce')
        ));
    }

    /**
     * Valida se todos os campos ACF necessários existem
     */
    public function validate_fields() {
        if (!function_exists('acf_get_field')) {
            return array('status' => 'error', 'message' => 'ACF não está ativo');
        }

        global $post;
        if (!$post) {
            return array('status' => 'error', 'message' => 'Post não encontrado');
        }

        // Lista de campos obrigatórios
        $required_fields = array(
            'tour_title',
            'hero_image',
            'greeting',
            'offer_description',
            'product_name',
            'start_date',
            'price_per_person',
            'quantity'
        );

        $missing_fields = array();
        foreach ($required_fields as $field_name) {
            $value = get_field($field_name);
            if (empty($value)) {
                $missing_fields[] = $field_name;
            }
        }

        if (!empty($missing_fields)) {
            return array(
                'status' => 'warning',
                'message' => 'Campos obrigatórios faltando: ' . implode(', ', $missing_fields),
                'fields' => $missing_fields
            );
        }

        return array('status' => 'ok', 'message' => 'Todos os campos obrigatórios estão preenchidos');
    }

    /**
     * Método para gerar PDF da proposta (opcional)
     */
    public static function generate_pdf($post_id) {
        // Implementação de geração de PDF
        // Você pode usar bibliotecas como mPDF, TCPDF, etc.
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ic-campaign') {
            return false;
        }

        // Aqui você implementaria a geração do PDF
        // Por enquanto, retorna apenas a confirmação
        return array(
            'status' => 'success',
            'message' => 'PDF gerado com sucesso',
            'url' => '#'
        );
    }

    /**
     * Método para enviar proposta por email
     */
    public static function send_by_email($post_id, $email) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'ic-campaign') {
            return false;
        }

        // Prepara dados da proposta
        $subject = 'Sua Proposta de Viagem: ' . get_field('tour_title', $post_id);
        $message = self::prepare_email_content($post_id);

        // Envia email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $sent = wp_mail($email, $subject, $message, $headers);

        return array(
            'status' => $sent ? 'success' : 'error',
            'message' => $sent ? 'Email enviado com sucesso' : 'Erro ao enviar email',
            'email' => $email
        );
    }

    /**
     * Prepara conteúdo do email da proposta
     */
    private static function prepare_email_content($post_id) {
        // Simples - você pode criar um template de email separado
        ob_start();
        
        global $post;
        $post = get_post($post_id);
        setup_postdata($post);

        include plugin_dir_path(dirname(__FILE__)) . 'templates/proposta-email.php';

        wp_reset_postdata();
        return ob_get_clean();
    }
}

// Inicializa a classe quando o plugin carrega
if (!function_exists('planetaexo_init_proposta_display')) {
    function planetaexo_init_proposta_display() {
        new PlanetaExoPropostaDisplay();
    }
    add_action('plugins_loaded', 'planetaexo_init_proposta_display', 15);
}
