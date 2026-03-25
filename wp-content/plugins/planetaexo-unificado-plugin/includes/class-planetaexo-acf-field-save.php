<?php
/**
 * PlanetaExo — Botão "Salvar" individual por campo ACF
 *
 * Injeta um botão "💾 Salvar" ao lado de cada campo ACF nas propostas (ic-campaign).
 * Clicar no botão envia AJAX e salva APENAS aquele campo, sem depender
 * do botão Update principal do WordPress (que recria UUIDs e zera outros campos).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoACFFieldSave {

    public static function init(): void {
        // Enqueue JS/CSS apenas no editor de ic-campaign
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

        // Endpoint AJAX (autenticado)
        add_action( 'wp_ajax_pxo_save_field', [ __CLASS__, 'ajax_save_field' ] );
    }

    public static function enqueue( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'ic-campaign' ) return;

        // CSS inline
        $css = "
        .pxo-field-save-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 8px;
            padding: 4px 12px;
            background: #fff;
            border: 1px solid #26c6da;
            color: #26c6da;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            line-height: 1.5;
        }
        .pxo-field-save-btn:hover {
            background: #26c6da;
            color: #fff;
        }
        .pxo-field-save-btn.saving {
            opacity: .6;
            pointer-events: none;
        }
        .pxo-field-save-btn.saved {
            border-color: #46b450;
            color: #46b450;
        }
        .pxo-field-save-btn.error {
            border-color: #dc3232;
            color: #dc3232;
        }
        .pxo-field-save-wrap {
            margin-top: 6px;
            display: none !important; /* Oculta botões Salvar — reativar removendo esta linha */
        }
        ";
        wp_add_inline_style( 'acf-input', $css );

        // JS inline (depois do ACF carregar)
        $js_data = [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'pxo_save_field' ),
            'postId'  => get_the_ID(),
        ];

        wp_add_inline_script(
            'acf-input',
            'var pxoFieldSave = ' . wp_json_encode( $js_data ) . ';' . self::js(),
            'after'
        );
    }

    /** AJAX handler: salva um único campo ACF */
    public static function ajax_save_field(): void {
        if ( ! check_ajax_referer( 'pxo_save_field', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Nonce inválido.' ], 403 );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Sem permissão.' ], 403 );
        }

        $post_id    = absint( $_POST['post_id']    ?? 0 );
        $field_key  = sanitize_text_field( $_POST['field_key']  ?? '' );
        $field_name = sanitize_text_field( $_POST['field_name'] ?? '' );
        $value      = $_POST['value'] ?? '';

        if ( ! $post_id || get_post_type( $post_id ) !== 'ic-campaign' ) {
            wp_send_json_error( [ 'message' => 'Post inválido.' ], 400 );
        }

        if ( ! $field_name ) {
            wp_send_json_error( [ 'message' => 'field_name ausente.' ], 400 );
        }

        // Sanitiza de acordo com o tipo do campo
        $field_obj = $field_key ? get_field_object( $field_key, $post_id ) : null;
        $type      = $field_obj['type'] ?? 'text';

        switch ( $type ) {
            case 'wysiwyg':
            case 'textarea':
                // Permite HTML para WYSIWYG, texto puro para textarea
                $clean = ( $type === 'wysiwyg' )
                    ? wp_kses_post( $value )
                    : sanitize_textarea_field( $value );
                break;
            case 'number':
                $clean = is_numeric( $value ) ? $value + 0 : '';
                break;
            case 'date_picker':
                $clean = sanitize_text_field( $value );
                break;
            case 'relationship':
            case 'post_object':
                $clean = absint( $value );
                break;
            default:
                $clean = sanitize_text_field( $value );
        }

        // Salva via ACF (mantém a referência do field_key no _field_name)
        if ( $field_key && function_exists( 'update_field' ) ) {
            $result = update_field( $field_key, $clean, $post_id );
        } else {
            $result = update_post_meta( $post_id, $field_name, $clean );
        }

        // Invalida caches
        clean_post_cache( $post_id );
        wp_cache_delete( $post_id, 'post_meta' );

        if ( $result !== false ) {
            wp_send_json_success( [
                'message'     => 'Salvo com sucesso.',
                'field_name'  => $field_name,
                'saved_value' => $clean,
            ] );
        } else {
            // update_field retorna false quando valor não mudou — isso é OK
            wp_send_json_success( [
                'message'    => 'Sem alterações (valor já era igual).',
                'field_name' => $field_name,
                'no_change'  => true,
            ] );
        }
    }

    /** JavaScript injetado no admin */
    private static function js(): string {
        return <<<'JS'
(function() {
    if (typeof acf === 'undefined') return;

    /* ── Injeta botão em cada campo ACF ── */
    function addSaveButton(field) {
        var $field = field.$el;

        // Evita duplicar
        if ($field.find('.pxo-field-save-wrap').length) return;

        var fieldKey  = field.get('key')  || '';
        var fieldName = field.get('name') || field.get('key') || '';

        // Tenta pegar o label do objeto ACF; fallback: lê do DOM (mais confiável)
        var label = field.get('label') || '';
        if (!label) {
            label = $field.find('> .acf-label label, .acf-label label').first().text().trim();
        }
        if (!label) label = fieldName; // último recurso

        // Para campos de produto (descricao_produto_N / data_da_viagem_N),
        // sobrescreve o label genérico pelo nome semântico correto
        var mDesc = fieldName.match(/^descricao_produto_(\d+)$/);
        var mData = fieldName.match(/^data_da_viagem_(\d+)$/);
        if (mDesc) {
            label = 'Proposta: Informa\u00e7\u00f5es sobre o Produto ' + mDesc[1];
        } else if (mData) {
            label = 'Data da Viagem \u2014 Produto ' + mData[1];
        }

        var $wrap = jQuery('<div class="pxo-field-save-wrap"></div>');
        var $btn  = jQuery(
            '<button type="button" class="pxo-field-save-btn" ' +
            'data-field-key="' + fieldKey + '" ' +
            'data-field-name="' + fieldName + '" ' +
            'data-label="' + jQuery('<div>').text(label).html() + '">' +
            '💾 Salvar "' + jQuery('<div>').text(label).html() + '"' +
            '</button>'
        );

        $wrap.append($btn);
        $field.find('.acf-input').first().append($wrap);

        $btn.on('click', function() {
            saveField(field, $btn);
        });
    }

    /* ── Coleta valor do campo e envia via AJAX ── */
    function saveField(field, $btn) {
        var fieldKey  = $btn.data('field-key');
        var fieldName = $btn.data('field-name');
        var type      = field.get('type');
        var value;

        // Sincroniza TinyMCE antes de pegar valor (WYSIWYG)
        if (type === 'wysiwyg' && typeof tinymce !== 'undefined') {
            var editorId = 'acf-' + fieldKey;
            var editor   = tinymce.get(editorId);
            if (!editor) {
                // TinyMCE pode usar ID diferente — tenta pegar pelo field
                tinymce.editors.forEach(function(ed) {
                    if (ed && field.$el.find('#' + ed.id).length) {
                        editor = ed;
                    }
                });
            }
            if (editor && !editor.isHidden()) {
                editor.save(); // sincroniza o textarea
            }
        }

        // Obtém valor do ACF
        value = field.val();

        // Fallback: tenta pegar direto do DOM para casos simples
        if (value === undefined || value === null) {
            var $input = field.$el.find('input, textarea, select').first();
            value = $input.val() || '';
        }

        // Serializa arrays/objetos
        if (typeof value === 'object' && value !== null) {
            value = JSON.stringify(value);
        }

        $btn.addClass('saving').text('Salvando...');

        jQuery.post(pxoFieldSave.ajaxUrl, {
            action:     'pxo_save_field',
            nonce:      pxoFieldSave.nonce,
            post_id:    pxoFieldSave.postId,
            field_key:  fieldKey,
            field_name: fieldName,
            value:      value
        })
        .done(function(resp) {
            var label = $btn.data('label') || $btn.data('field-name');
            if (resp.success) {
                var msg = resp.data.no_change ? '✓ Sem alterações' : '✓ Salvo!';
                $btn.removeClass('saving').addClass('saved').text(msg);
                setTimeout(function() {
                    $btn.removeClass('saved').text('💾 Salvar "' + label + '"');
                }, 2500);
            } else {
                var errMsg = (resp.data && resp.data.message) ? resp.data.message : 'Erro';
                $btn.removeClass('saving').addClass('error').text('✗ ' + errMsg);
                setTimeout(function() {
                    $btn.removeClass('error').text('💾 Salvar "' + label + '"');
                }, 3000);
            }
        })
        .fail(function() {
            var label = $btn.data('label') || $btn.data('field-name');
            $btn.removeClass('saving').addClass('error').text('✗ Falha na requisição');
            setTimeout(function() {
                $btn.removeClass('error').text('💾 Salvar "' + label + '"');
            }, 3000);
        });
    }

    /* ── Registra o botão quando cada campo é renderizado ── */
    acf.addAction('ready_field', function(field) {
        addSaveButton(field);
    });

    acf.addAction('append_field', function(field) {
        addSaveButton(field);
    });

})();
JS;
    }
}
