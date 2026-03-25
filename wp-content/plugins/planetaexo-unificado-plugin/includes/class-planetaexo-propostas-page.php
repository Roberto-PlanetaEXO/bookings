<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PlanetaExoPropostasPage {
    public static function register_menu_highlight_hooks() {
        add_filter('parent_file', [__CLASS__, 'set_parent_file']);
        add_filter('submenu_file', [__CLASS__, 'set_submenu_file']);
    }

    public static function set_parent_file($parent_file) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'ic-campaign') {
            return PlanetaExoPlugin::MENU_SLUG;
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
        if ($post_type === 'ic-campaign') {
            return PlanetaExoPlugin::MENU_SLUG;
        }

        return $parent_file;
    }

    public static function set_submenu_file($submenu_file) {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'ic-campaign') {
            return 'edit.php?post_type=ic-campaign';
        }

        $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash($_GET['post_type'])) : '';
        if ($post_type === 'ic-campaign') {
            return 'edit.php?post_type=ic-campaign';
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($post_id > 0 && get_post_type($post_id) === 'ic-campaign') {
            return 'edit.php?post_type=ic-campaign';
        }

        return $submenu_file;
    }

    public static function add_clone_column($columns) {
        $columns['duplicar_post'] = 'Clonar Proposta';
        return $columns;
    }

    public static function render_clone_column($column, $post_id) {
        if ($column !== 'duplicar_post') {
            return;
        }

        if (!current_user_can('edit_posts')) {
            return;
        }

        $url = wp_nonce_url(
            add_query_arg(
                [
                    'duplicar_post_id' => absint($post_id),
                ],
                admin_url('/')
            ),
            'planetaexo_clone_' . absint($post_id)
        );

        echo '<a href="' . esc_url($url) . '" class="button button-primary">Clone</a>';
    }

    public static function handle_clone_request() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        if (!isset($_GET['duplicar_post_id'])) {
            return;
        }

        $post_id = absint($_GET['duplicar_post_id']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, 'planetaexo_clone_' . $post_id)) {
            wp_die('Falha de segurança ao clonar proposta.');
        }

        $new_id = self::duplicate_post($post_id);
        if (is_wp_error($new_id)) {
            wp_die(esc_html($new_id->get_error_message()));
        }

        wp_safe_redirect(admin_url('post.php?post=' . absint($new_id) . '&action=edit&classic-editor'));
        exit;
    }

    private static function duplicate_post($post_id) {
        $post = get_post($post_id);

        if (!$post) {
            return new WP_Error('post_not_found', 'Post não encontrado');
        }

        if (!post_type_exists('ic-campaign')) {
            return new WP_Error('post_type_not_found', "Erro: O tipo de post 'ic-campaign' não existe.");
        }

        $new_post = [
            'post_title' => $post->post_title . ' (Copia)',
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $post->post_type,
            'post_author' => get_current_user_id(),
            'post_category' => wp_get_post_categories($post_id),
            'post_parent' => $post->post_parent,
        ];

        $new_post_id = wp_insert_post($new_post, true);
        if (is_wp_error($new_post_id)) {
            return $new_post_id;
        }

        $meta_data = get_post_meta($post_id);
        foreach ($meta_data as $meta_key => $meta_values) {
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }

        return $new_post_id;
    }
}
