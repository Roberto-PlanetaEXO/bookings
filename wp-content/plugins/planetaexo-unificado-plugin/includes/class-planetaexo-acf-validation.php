<?php
/**
 * Classe para validação de campos ACF em propostas
 * 
 * @package PlanetaExo
 */

class PlanetaExoACFValidation {
    
    const ACF_REQUIRED_FIELDS = array(
        'data_da_viagem',
        'descricao_detalhada',
        'data_de_validade_da_proposta'
    );
    
    public static function init() {
        add_action('save_post_ic_campaign', array(__CLASS__, 'validate_before_save'), 10, 3);
        add_action('admin_notices', array(__CLASS__, 'display_validation_notices'));
    }
    
    /**
     * Valida campos ACF antes de salvar a proposta
     */
    public static function validate_before_save($post_id, $post, $update) {
        // Evita estar em auto-save
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Verifica permissão
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Executa validação
        $errors = self::validate_acf_fields($post_id);
        
        if (!empty($errors)) {
            set_transient('planetaexo_validation_errors_' . $post_id, $errors, 30);
            
            // Redireciona de volta para edição com mensagem de erro
            wp_safe_redirect(get_edit_post_link($post_id));
            exit;
        }
    }
    
    /**
     * Valida se os campos ACF required estão preenchidos
     */
    private static function validate_acf_fields($post_id) {
        $errors = array();
        
        if (!function_exists('get_field')) {
            return $errors; // ACF não está ativo
        }
        
        // Verifica campos obrigatórios
        foreach (self::ACF_REQUIRED_FIELDS as $field_name) {
            $value = get_field($field_name, $post_id);
            
            if (empty($value)) {
                $field_object = get_field_object($field_name, $post_id);
                $field_label = $field_object['label'] ?? ucfirst(str_replace('_', ' ', $field_name));
                $errors[] = sprintf('O campo "%s" é obrigatório.', $field_label);
            }
        }
        
        return $errors;
    }
    
    /**
     * Exibe mensagens de validação no admin
     */
    public static function display_validation_notices() {
        global $post;
        
        if (!$post || $post->post_type !== 'ic-campaign') {
            return;
        }
        
        $errors = get_transient('planetaexo_validation_errors_' . $post->ID);
        
        if ($errors && is_array($errors)) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>Erro de validação:</strong> ' . esc_html($error) . '</p>';
                echo '</div>';
            }
            delete_transient('planetaexo_validation_errors_' . $post->ID);
        }
    }
    
    /**
     * Retorna lista de campos ACF configurados para ic-campaign
     */
    public static function get_configured_acf_fields() {
        if (!function_exists('acf_get_field_groups')) {
            return array();
        }
        
        $field_groups = acf_get_field_groups(array(
            'post_type' => 'ic-campaign'
        ));
        
        $fields = array();
        
        foreach ($field_groups as $group) {
            $group_fields = acf_get_fields($group['ID']);
            
            foreach ($group_fields as $field) {
                $fields[$field['name']] = array(
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => isset($field['required']) ? $field['required'] : false,
                    'instructions' => $field['instructions'] ?? ''
                );
            }
        }
        
        return $fields;
    }
    
    /**
     * Valida e retorna todas as propostas com campos faltando
     */
    public static function find_incomplete_proposals() {
        global $wpdb;
        
        $proposals = get_posts(array(
            'post_type' => 'ic-campaign',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        $incomplete = array();
        
        foreach ($proposals as $proposal) {
            $errors = self::validate_acf_fields($proposal->ID);
            
            if (!empty($errors)) {
                $incomplete[] = array(
                    'id' => $proposal->ID,
                    'title' => $proposal->post_title,
                    'errors' => $errors
                );
            }
        }
        
        return $incomplete;
    }
}

// Inicializa a validação
if (function_exists('add_action')) {
    PlanetaExoACFValidation::init();
}
