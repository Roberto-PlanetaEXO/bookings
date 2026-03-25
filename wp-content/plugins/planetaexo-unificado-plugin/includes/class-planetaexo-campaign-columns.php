<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adiciona colunas extras na listagem de propostas (ic-campaign) no admin do WordPress.
 * - Data de Expiração (ACF: data_de_validade_da_proposta)
 */
final class PlanetaExoCampaignColumns {

    /** Chave do campo ACF que armazena a data de validade (formato: Ymd) */
    const ACF_EXPIRATION = 'data_de_validade_da_proposta';

    public static function init(): void {
        // Prioridade 20: roda após o Cart Link (prioridade 10), garantindo que
        // a coluna 'status' já existe quando tentamos inserir após ela.
        add_filter('manage_ic-campaign_posts_columns',          [self::class, 'add_columns'], 20);
        add_action('manage_ic-campaign_posts_custom_column',    [self::class, 'render_column'], 10, 2);
        add_filter('manage_edit-ic-campaign_sortable_columns',  [self::class, 'sortable_columns'], 20);
        add_action('pre_get_posts',                             [self::class, 'handle_sort']);
        add_action('admin_head',                                [self::class, 'inline_styles']);
    }

    /**
     * Insere a coluna "Data de Expiração" após a coluna "Enabled".
     */
    public static function add_columns(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            // Insere após a coluna "status" (Enabled)
            if ($key === 'status') {
                $new['pxo_expiration'] = 'Data de Expiração';
            }
        }
        // Fallback: se 'status' não existir, adiciona no final antes de 'url'
        if (!isset($new['pxo_expiration'])) {
            $final = [];
            foreach ($new as $key => $label) {
                if ($key === 'url') {
                    $final['pxo_expiration'] = 'Data de Expiração';
                }
                $final[$key] = $label;
            }
            return $final ?: array_merge($new, ['pxo_expiration' => 'Data de Expiração']);
        }
        return $new;
    }

    /**
     * Renderiza o conteúdo da coluna para cada proposta.
     */
    public static function render_column(string $column, int $post_id): void {
        if ($column !== 'pxo_expiration') {
            return;
        }

        $raw = '';

        // Tenta via função ACF (se disponível)
        if (function_exists('get_field')) {
            $raw = get_field(self::ACF_EXPIRATION, $post_id);
        }

        // Fallback direto via post_meta (ACF salva como Ymd)
        if (empty($raw)) {
            $raw = get_post_meta($post_id, self::ACF_EXPIRATION, true);
        }

        if (empty($raw)) {
            echo '<span class="pxo-exp pxo-exp--none">Sem data</span>';
            return;
        }

        $date = self::parse_date($raw);

        if (!$date) {
            echo '<span class="pxo-exp pxo-exp--none">Sem data</span>';
            return;
        }

        // Usa current_time('Ymd') para respeitar o timezone do WP
        // e evitar que datas passadas apareçam como "hoje" por diferença de UTC
        $today_ymd = current_time('Ymd');
        $date_ymd  = $date->format('Ymd');
        $formatted = $date->format('d/m/Y');

        $is_past   = $date_ymd < $today_ymd;
        $is_today  = $date_ymd === $today_ymd;

        // Calcula diff em dias apenas para exibição do badge
        $days_diff = (int) round((strtotime($date_ymd) - strtotime($today_ymd)) / 86400);
        $days_diff = abs($days_diff);

        if ($is_today) {
            $icon  = '⚠️';
            $mod   = 'pxo-exp--today';
            $badge = 'Expira hoje';
        } elseif ($is_past) {
            $icon  = '✕';
            $mod   = 'pxo-exp--expired';
            $badge = $days_diff === 1 ? 'Expirou ontem' : "Expirou há {$days_diff} dias";
        } elseif ($days_diff <= 7) {
            $icon  = '⚠';
            $mod   = 'pxo-exp--soon';
            $badge = $days_diff === 1 ? 'Amanhã' : "Em {$days_diff} dias";
        } else {
            $icon  = '✓';
            $mod   = 'pxo-exp--ok';
            $badge = "Em {$days_diff} dias";
        }

        echo '<div class="pxo-exp ' . esc_attr($mod) . '">'
            . '<span class="pxo-exp__date">' . esc_html($formatted) . '</span>'
            . '<span class="pxo-exp__badge">'
            .   '<span class="pxo-exp__icon">' . $icon . '</span>'
            .   esc_html($badge)
            . '</span>'
            . '</div>';
    }

    /**
     * Torna a coluna ordenável.
     */
    public static function sortable_columns(array $columns): array {
        $columns['pxo_expiration'] = 'pxo_expiration';
        return $columns;
    }

    /**
     * Aplica ordenação quando a coluna é selecionada.
     */
    public static function handle_sort(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('post_type') !== 'ic-campaign') {
            return;
        }
        if ($query->get('orderby') !== 'pxo_expiration') {
            return;
        }
        $query->set('meta_key', self::ACF_EXPIRATION);
        $query->set('orderby', 'meta_value');
    }

    /**
     * CSS inline para os badges de expiração.
     */
    public static function inline_styles(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'ic-campaign') {
            return;
        }
        echo '<style>
            .column-pxo_expiration { width: 140px; }

            .pxo-exp {
                display: inline-flex;
                flex-direction: column;
                gap: 4px;
            }

            .pxo-exp__date {
                font-size: 13px;
                font-weight: 600;
                color: #1e1e1e;
                letter-spacing: 0.01em;
            }

            .pxo-exp__badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 20px;
                letter-spacing: 0.02em;
                width: fit-content;
            }

            .pxo-exp__icon {
                font-style: normal;
                font-size: 10px;
                line-height: 1;
            }

            /* Expirada */
            .pxo-exp--expired .pxo-exp__date  { color: #7f1d1d; }
            .pxo-exp--expired .pxo-exp__badge  { background: #fee2e2; color: #991b1b; }

            /* Expira hoje */
            .pxo-exp--today .pxo-exp__date     { color: #78350f; }
            .pxo-exp--today .pxo-exp__badge    { background: #fef3c7; color: #92400e; }

            /* Expira em breve (≤7 dias) */
            .pxo-exp--soon .pxo-exp__date      { color: #7c2d12; }
            .pxo-exp--soon .pxo-exp__badge     { background: #ffedd5; color: #9a3412; }

            /* Válida */
            .pxo-exp--ok .pxo-exp__date        { color: #1e1e1e; }
            .pxo-exp--ok .pxo-exp__badge       { background: #dcfce7; color: #166534; }

            /* Sem data */
            .pxo-exp--none {
                font-size: 12px;
                color: #9ca3af;
                font-style: italic;
            }

            /* Ordenação via header */
            th#pxo_expiration a { white-space: nowrap; }
        </style>';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Converte string de data (Ymd ou Y-m-d ou d/m/Y) em DateTime, ou null se inválida.
     */
    private static function parse_date(string $raw): ?DateTime {
        // Formato ACF padrão: Ymd → "20260315"
        if (preg_match('/^\d{8}$/', $raw)) {
            $d = DateTime::createFromFormat('Ymd', $raw);
            return $d ?: null;
        }
        // ISO: Y-m-d → "2026-03-15"
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $d = DateTime::createFromFormat('Y-m-d', $raw);
            return $d ?: null;
        }
        // BR: d/m/Y → "15/03/2026"
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
            $d = DateTime::createFromFormat('d/m/Y', $raw);
            return $d ?: null;
        }
        return null;
    }
}
