<?php
/**
 * PlanetaExo — Diagnóstico de Save da Proposta
 *
 * Rastreia o que acontece com os campos ACF e post_meta durante o save de ic-campaign.
 * Foca no bug: campos somem ao trocar agente ou data de validade.
 *
 * Página admin: Planeta Exo → 🔍 Debug Save
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PlanetaExoDebugSave {

    const LOG_FILE  = 'pxo-save-debug.log';
    const MAX_LINES = 1000;
    const NONCE     = 'pxo_debug_save_clear';

    // Campos ACF críticos que monitoramos
    const WATCH_KEYS = [
        'products',
        'redirect_to',
        'clear_cart',
        'agente_responsavel',
        'data_de_validade_da_proposta',
        'descricao_da_viagem',
        'tour_title',
        'guest_names',
        'data_da_viagem_1',
        'descricao_produto_1',
        'price_per_person',
        'total_price',
        'quantity',
    ];

    public static function init(): void {
        // ── Antes do save (captura estado atual dos campos) ──────────────────
        add_action( 'pre_post_update',         [ __CLASS__, 'before_post_update'  ], 1, 2 );
        add_action( 'acf/save_post',           [ __CLASS__, 'before_acf_save'     ], 1    ); // prio 1 = antes do ACF salvar
        add_action( 'acf/save_post',           [ __CLASS__, 'after_acf_save'      ], 99   ); // prio 99 = depois do ACF salvar

        // ── Após o save (verifica o que ficou no DB) ─────────────────────────
        add_action( 'save_post_ic-campaign',   [ __CLASS__, 'after_post_save'     ], 999, 1 );

        // ── Log de update_post_meta (qualquer chamada durante ic-campaign) ───
        add_action( 'update_post_meta',        [ __CLASS__, 'on_update_meta'      ], 1, 4  );
        add_action( 'added_post_meta',         [ __CLASS__, 'on_added_meta'       ], 1, 4  );
        add_action( 'deleted_post_meta',       [ __CLASS__, 'on_deleted_meta'     ], 1, 4  );

        // ── Página de admin ──────────────────────────────────────────────────
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
    }

    /* ─────────────────────────────────────────────────────────
     * HOOKS DE DIAGNÓSTICO
     * ───────────────────────────────────────────────────────── */

    /** Captura snapshot dos campos críticos ANTES do WordPress salvar o post */
    public static function before_post_update( int $post_id, array $data ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        self::log( '═══════════════════════════════════════════════' );
        self::log( '💾 SAVE INICIADO — post_id=' . $post_id . ' post_status=' . ( $data['post_status'] ?? '?' ) );
        self::log( '   REQUEST método: ' . ( $_SERVER['REQUEST_METHOD'] ?? '?' ) );
        self::log( '   Campos POST enviados: ' . implode( ', ', array_keys( $_POST ) ) );
        self::log( '   ACF presente no POST: ' . ( isset( $_POST['acf'] ) ? 'SIM (' . count( $_POST['acf'] ) . ' campos)' : 'NÃO' ) );

        // Snapshot dos campos críticos no DB agora (antes de qualquer alteração)
        wp_cache_delete( $post_id, 'post_meta' );
        self::log( '   ── SNAPSHOT ANTES (DB direto) ──' );
        foreach ( self::WATCH_KEYS as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            self::log( sprintf( '   [%-40s] = %s', $key, self::preview( $val ) ) );
        }
    }

    /** Captura o que o ACF está prestes a salvar (campos $_POST['acf']) */
    public static function before_acf_save( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        self::log( '   ── ACF/SAVE_POST prio 1 (o que ACF vai salvar) ──' );
        if ( ! empty( $_POST['acf'] ) && is_array( $_POST['acf'] ) ) {
            foreach ( $_POST['acf'] as $field_key => $value ) {
                self::log( sprintf( '   acf[%-35s] = %s', $field_key, self::preview( $value ) ) );
            }
        } else {
            self::log( '   $_POST[acf] está VAZIO — ACF não vai salvar nenhum campo!' );
        }
    }

    /** Captura o que ficou no DB logo após o ACF salvar */
    public static function after_acf_save( $post_id ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        wp_cache_delete( $post_id, 'post_meta' );
        self::log( '   ── SNAPSHOT APÓS ACF SALVAR (prio 99) ──' );
        foreach ( self::WATCH_KEYS as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            self::log( sprintf( '   [%-40s] = %s', $key, self::preview( $val ) ) );
        }
    }

    /** Final: verifica o estado completo após todos os hooks de save */
    public static function after_post_save( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        wp_cache_delete( $post_id, 'post_meta' );
        self::log( '   ── ESTADO FINAL (save_post_ic-campaign, prio 999) ──' );
        foreach ( self::WATCH_KEYS as $key ) {
            $val = get_post_meta( $post_id, $key, true );
            self::log( sprintf( '   [%-40s] = %s', $key, self::preview( $val ) ) );
        }
        self::log( '✅ SAVE CONCLUÍDO — post_id=' . $post_id );
    }

    /** Loga cada chamada update_post_meta nos campos críticos */
    public static function on_update_meta( $meta_id, int $post_id, string $meta_key, $meta_value ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( ! in_array( $meta_key, self::WATCH_KEYS, true ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        self::log( sprintf(
            '   📝 update_post_meta(%d, "%s") → %s  [chamado por: %s]',
            $post_id, $meta_key, self::preview( $meta_value ),
            self::caller_hint()
        ) );
    }

    /** Loga cada added_post_meta nos campos críticos */
    public static function on_added_meta( $meta_id, int $post_id, string $meta_key, $meta_value ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( ! in_array( $meta_key, self::WATCH_KEYS, true ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        self::log( sprintf(
            '   ➕ added_post_meta(%d, "%s") → %s  [chamado por: %s]',
            $post_id, $meta_key, self::preview( $meta_value ),
            self::caller_hint()
        ) );
    }

    /** Loga cada deleted_post_meta nos campos críticos */
    public static function on_deleted_meta( $meta_ids, int $post_id, string $meta_key, $meta_value ): void {
        if ( get_post_type( $post_id ) !== 'ic-campaign' ) return;
        if ( ! in_array( $meta_key, self::WATCH_KEYS, true ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        self::log( sprintf(
            '   🗑  deleted_post_meta(%d, "%s")  [chamado por: %s]',
            $post_id, $meta_key,
            self::caller_hint()
        ) );
    }

    /* ─────────────────────────────────────────────────────────
     * PÁGINA DE ADMIN
     * ───────────────────────────────────────────────────────── */

    public static function register_menu(): void {
        add_submenu_page(
            'planeta-exo',
            '🔍 Debug Save',
            '🔍 Debug Save',
            'manage_options',
            'pxo-debug-save',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        if ( isset( $_POST['pxo_clear_log'] ) && check_admin_referer( self::NONCE ) ) {
            file_put_contents( self::log_path(), '' );
            echo '<div class="notice notice-success"><p>Log apagado.</p></div>';
        }

        $log_path = self::log_path();
        $content  = file_exists( $log_path ) ? file_get_contents( $log_path ) : '';

        echo '<div class="wrap">';
        echo '<h1>🔍 Debug Save — Campos ACF durante update da Proposta</h1>';
        echo '<p><strong>Instruções:</strong> abra uma proposta no admin → troque o agente ou a data → clique Update → volte aqui.</p>';
        echo '<p style="color:#666;font-size:12px">Log em: <code>' . esc_html( $log_path ) . '</code></p>';

        echo '<form method="post" style="margin-bottom:12px">';
        wp_nonce_field( self::NONCE );
        echo '<button name="pxo_clear_log" value="1" class="button button-secondary">🗑 Apagar log</button>';
        echo '</form>';

        if ( $content ) {
            $lines = array_reverse( array_filter( explode( "\n", $content ) ) );
            echo '<textarea readonly style="width:100%;height:700px;font-family:monospace;font-size:11px;background:#1e1e1e;color:#9cdcfe;padding:12px;border:none">';
            echo esc_textarea( implode( "\n", $lines ) );
            echo '</textarea>';
        } else {
            echo '<p style="color:#999">Nenhum dado no log ainda. Salve uma proposta para gerar registros.</p>';
        }

        echo '</div>';
    }

    /* ─────────────────────────────────────────────────────────
     * HELPERS INTERNOS
     * ───────────────────────────────────────────────────────── */

    private static function preview( $value, int $max = 120 ): string {
        if ( $value === null )  return 'NULL';
        if ( $value === false ) return 'FALSE';
        if ( $value === '' )    return '(vazio)';
        if ( is_array( $value ) ) {
            $json = wp_json_encode( $value );
            return strlen( $json ) > $max ? substr( $json, 0, $max ) . '…' : $json;
        }
        $str = (string) $value;
        return strlen( $str ) > $max ? substr( $str, 0, $max ) . '…' : $str;
    }

    /**
     * Retorna a função/arquivo que chamou update_post_meta (para rastrear quem apaga os campos).
     * Sobe pela call stack até encontrar algo fora do WordPress core.
     */
    private static function caller_hint(): string {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
        $hints = [];
        foreach ( $trace as $frame ) {
            $file = $frame['file'] ?? '';
            // Ignora core WP e o próprio debug
            if ( strpos( $file, '/wp-includes/' ) !== false ) continue;
            if ( strpos( $file, 'class-planetaexo-debug' ) !== false ) continue;
            $short = basename( $file );
            $line  = $frame['line'] ?? 0;
            $fn    = ( $frame['class'] ?? '' ) . ( $frame['type'] ?? '' ) . ( $frame['function'] ?? '' );
            $hints[] = "{$short}:{$line} {$fn}";
            if ( count( $hints ) >= 3 ) break;
        }
        return empty( $hints ) ? 'desconhecido' : implode( ' → ', $hints );
    }

    private static function log( string $message ): void {
        $path = self::log_path();
        $line = '[' . date( 'H:i:s' ) . '] ' . $message . "\n";

        if ( file_exists( $path ) ) {
            $lines = file( $path );
            if ( count( $lines ) > self::MAX_LINES ) {
                $lines = array_slice( $lines, -(int)( self::MAX_LINES / 2 ) );
                file_put_contents( $path, implode( '', $lines ) );
            }
        }

        file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
    }

    private static function log_path(): string {
        return plugin_dir_path( dirname( __FILE__ ) ) . self::LOG_FILE;
    }
}
