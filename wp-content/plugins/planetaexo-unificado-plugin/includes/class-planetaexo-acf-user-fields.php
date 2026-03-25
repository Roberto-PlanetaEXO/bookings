<?php
/**
 * Registra o grupo de campos ACF "Agentes (Usuários)" no perfil
 * de todos os usuários do WordPress.
 *
 * Os campos registrados aqui aparecem na tela de edição de usuário
 * (/wp-admin/user-edit.php e /wp-admin/profile.php).
 *
 * Meta keys usados  (também lidos por pxo_get_agent() no tema):
 *   – agent_photo       → imagem do agente
 *   – agent_whatsapp    → número WhatsApp com DDI (ex: +5511949084101)
 *   – link_agendamento  → URL do calendário (ex: Google Calendar)
 *
 * @package PlanetaExo
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoACFUserFields {

    public static function init(): void {
        add_action( 'acf/init', [ __CLASS__, 'register_field_group' ] );
    }

    public static function register_field_group(): void {
        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        // ── Campo "Agente Responsável" na proposta (ic-campaign) ──────────
        acf_add_local_field_group( [
            'key'    => 'group_pxo_agente_proposta',
            'title'  => 'Agente Responsável',
            'fields' => [
                [
                    'key'           => 'field_pxo_agente_responsavel',
                    'label'         => 'Agente Responsável',
                    'name'          => 'agente_responsavel',
                    'type'          => 'user',
                    'instructions'  => 'Selecione o agente que aparece na proposta. Se vazio, usa o autor do post.',
                    'required'      => 0,
                    'role'          => '',        // qualquer role
                    'allow_null'    => 1,
                    'multiple'      => 0,
                    'return_format' => 'array',   // retorna array com ID, display_name etc.
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'ic-campaign',
                    ],
                ],
            ],
            'menu_order'      => 0,
            'position'        => 'side',
            'style'           => 'default',
            'label_placement' => 'top',
            'active'          => true,
        ] );

        // ── Campos de perfil "Agentes (Usuários)" ─────────────────────────
        acf_add_local_field_group( [
            'key'                   => 'group_pxo_agentes_usuarios',
            'title'                 => 'Agentes (Usuários)',
            'fields'                => [

                // ── Foto ────────────────────────────────────────────────
                [
                    'key'           => 'field_pxo_agent_photo',
                    'label'         => 'Foto',
                    'name'          => 'agent_photo',
                    'type'          => 'image',
                    'instructions'  => 'Foto de perfil do agente exibida na página de proposta.',
                    'required'      => 0,
                    'return_format' => 'array',   // retorna array com url, width, height…
                    'preview_size'  => 'thumbnail',
                    'library'       => 'all',
                ],

                // ── Telefone / WhatsApp ──────────────────────────────────
                [
                    'key'           => 'field_pxo_agent_whatsapp',
                    'label'         => 'Telefone / WhatsApp',
                    'name'          => 'agent_whatsapp',
                    'type'          => 'text',
                    'instructions'  => 'Número com código do país, ex: +5511949084101',
                    'required'      => 0,
                    'placeholder'   => '+5511999999999',
                ],

                // ── Link para agendamento ────────────────────────────────
                [
                    'key'           => 'field_pxo_link_agendamento',
                    'label'         => 'Link para agendamento',
                    'name'          => 'link_agendamento',
                    'type'          => 'url',
                    'instructions'  => 'URL para agendamento de chamada (Google Calendar, Calendly, etc.).',
                    'required'      => 0,
                    'placeholder'   => 'https://calendar.app.google/...',
                ],

            ],
            'location'              => [
                [
                    [
                        'param'    => 'user_form',
                        'operator' => '==',
                        'value'    => 'all',   // perfil + edição de usuário
                    ],
                ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ] );
    }
}
