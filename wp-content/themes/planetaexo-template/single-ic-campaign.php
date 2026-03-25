<?php
/**
 * Template exclusivo para Propostas (ic-campaign)
 * Layout independente — sem herdar header/footer do tema
 */
if (!defined('ABSPATH')) exit;

if (!have_posts()) { wp_redirect(home_url()); exit; }
the_post();

$post_id = get_the_ID();

// ── ACF Fields ──────────────────────────────────────────
$tour_title          = get_field('tour_title',         $post_id) ?: get_the_title();
$hero_image          = get_field('hero_image',          $post_id);
$guest_names         = get_field('guest_names',         $post_id);
$greeting            = get_field('greeting',            $post_id);
$offer_description   = get_field('offer_description',   $post_id);
// ── Campos do grupo "Informações da Proposta" (campos reais do DB) ──
$descricao_da_viagem          = pxo_field('descricao_da_viagem',          $post_id);
$data_de_validade_da_proposta = pxo_field('data_de_validade_da_proposta', $post_id);
// ── Outros campos de exibição ──
$important_note      = get_field('important_note',      $post_id);
$cancellation_policy = get_field('cancellation_policy', $post_id);
// Usa a descrição da proposta ou o offer_description como fallback
if (!$offer_description && $descricao_da_viagem) {
    $offer_description = $descricao_da_viagem;
}

// ── Produtos da campanha (meta do Cart Link) ──────────────
$products_meta = get_post_meta($post_id, 'products', true);
if (!is_array($products_meta)) $products_meta = [];

// Prepara lista de produtos com campos ACF por índice (1-based)
// Após o PlanetaExoProductsNormalizer rodar no save, as chaves são sempre
// "1","2","3"... estáveis. Para propostas antigas (UUIDs), aplicamos
// array_values() como fallback para converter para 0,1,2...
$products_raw = is_array( $products_meta ) ? $products_meta : [];
// Detecta se as chaves já são numéricas sequenciais (normalizadas)
$keys = array_keys( $products_raw );
$already_normalized = ! empty( $keys ) && ( $keys[0] === '1' || $keys[0] === 1 );
if ( ! $already_normalized ) {
    $products_raw = array_values( $products_raw ); // fallback UUID→sequencial
}

// Mapeamento produto→slot ACF salvo pelo Product Linker (admin).
// Estrutura: { "1": 2, "2": 1, ... } → produto na posição N usa campos ACF do slot M.
// Se não existir mapeamento, usa posição como slot (comportamento original).
$acf_mapping_raw = get_post_meta( $post_id, 'pxo_product_acf_mapping', true );
$acf_mapping = is_array( $acf_mapping_raw ) ? $acf_mapping_raw : [];

$produtos = [];
foreach ( $products_raw as $key => $product_data ) {
    if ( ! is_array( $product_data ) ) continue;
    // Se normalizado, chaves são "1","2","3"... Se não, 0,1,2...
    $n   = $already_normalized ? (int) $key : (int) $key + 1;
    // Slot ACF: usa o mapeamento se disponível, caso contrário usa a posição
    $acf_slot = isset( $acf_mapping[ $n ] ) ? (int) $acf_mapping[ $n ] : $n;
    $pid = isset($product_data['product_id']) ? (int)$product_data['product_id'] : 0;
    $wc  = $pid ? wc_get_product($pid) : null;
    // Garante que price seja sempre float (nunca string nem null)
    $raw_price = isset($product_data['price']) ? $product_data['price'] : -1;
    if ($raw_price == -1 || !is_numeric($raw_price)) {
        $price = $wc ? (float)$wc->get_price() : 0.0;
    } else {
        $price = (float)$raw_price;
    }
    $raw_name = $wc ? (string)$wc->get_name() : '';
    // Remove parte após " – " ou " - " (ex: "AMAZON JUNGLE TOUR 3 DAYS – CHALET DOLPHIN TPL 2 SMT" → "AMAZON JUNGLE TOUR 3 DAYS")
    $nome = preg_replace('/\s+[–\-]\s+.*$/u', '', trim($raw_name)) ?: $raw_name;
    $produtos[] = [
        'n'            => $n,
        'acf_slot'     => $acf_slot,
        'product_id'   => $pid,
        'nome'         => $nome,
        'quantity'     => isset($product_data['quantity']) ? (int)$product_data['quantity'] : 1,
        'price'        => $price,
        'data_viagem'  => (string)(pxo_field('data_da_viagem_'    . $acf_slot, $post_id) ?? ''),
        'descricao'    => (string)(pxo_field('descricao_produto_' . $acf_slot, $post_id) ?? ''),
    ];
}

// Totais consolidados
$total_price = array_reduce($produtos, function($carry, $p) {
    return (float)$carry + ((float)$p['price'] * (int)$p['quantity']);
}, 0.0);
$quantity = (int)array_sum(array_column($produtos, 'quantity'));

// Campos de compatibilidade (para proposta com 1 produto)
$primeiro_produto  = $produtos[0] ?? [];
$product_name      = !empty($primeiro_produto['nome'])        ? (string)$primeiro_produto['nome']        : (string)(get_field('product_name', $post_id) ?? '');
$start_date        = !empty($primeiro_produto['data_viagem']) ? (string)$primeiro_produto['data_viagem'] : (string)(get_field('start_date', $post_id) ?? '');
$price_per_person  = !empty($primeiro_produto['price'])       ? (float)$primeiro_produto['price']       : (float)(get_field('price_per_person', $post_id) ?? 0);
if (!$total_price) $total_price = (float)(get_field('total_price', $post_id) ?? 0) ?: ((float)$quantity * $price_per_person);

// ── Validade da proposta ──────────────────────────────────
// Compara apenas a DATA (sem horário) no timezone do WordPress.
// Usa current_time('Ymd') para evitar falsos positivos por fuso horário.
// A proposta só expira APÓS o dia de validade (não no dia em si).
$proposta_expirada = false;
if ($data_de_validade_da_proposta) {
    $val_str = trim((string) $data_de_validade_da_proposta);

    // Normaliza qualquer formato → Ymd (string comparável)
    $val_ymd = '';
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $val_str, $m)) {
        $val_ymd = $m[3] . $m[2] . $m[1]; // d/m/Y → Ymd
    } elseif (preg_match('/^\d{8}$/', $val_str)) {
        $val_ymd = $val_str;               // Ymd direto
    } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $val_str, $m)) {
        $val_ymd = $m[1] . $m[2] . $m[3]; // Y-m-d → Ymd
    }

    if ($val_ymd) {
        // current_time('Ymd') respeita o timezone configurado no WP
        // Ex: validade=20260314, hoje=20260314 → igual → NÃO expirado
        //     validade=20260313, hoje=20260314 → menor → expirado
        $proposta_expirada = ($val_ymd < current_time('Ymd'));
    }
}

/**
 * Formata data que pode vir em YYYYMMDD ou d/m/Y
 */
if ( ! function_exists( 'pxo_format_date' ) ) :
function pxo_format_date( $raw ): string {
    $raw = (string) $raw;
    if (!$raw) return '';
    $dt = DateTime::createFromFormat('Ymd', $raw)
       ?: DateTime::createFromFormat('d/m/Y', $raw)
       ?: DateTime::createFromFormat('Y-m-d', $raw);
    return $dt ? $dt->format('d/m/Y') : esc_html($raw);
}
endif;

/**
 * Lê campo ACF com fallback para get_post_meta() direto
 */
if ( ! function_exists( 'pxo_field' ) ) :
function pxo_field( $field_name, $post_id, $format_value = true ) {
    $value = null;
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field_name, $post_id, $format_value );
    }
    if ( $value === null || $value === false || $value === '' ) {
        $value = get_post_meta( $post_id, $field_name, true );
    }
    return $value;
}
endif;

$agent = pxo_get_agent($post_id);

// Hero background — ACF field, depois imagem do produto WooCommerce, depois post thumbnail
$hero_url = '';
if ($hero_image) {
    $hero_url = is_array($hero_image) ? ($hero_image['url'] ?? '') : (string) wp_get_attachment_url($hero_image);
}
// Fallback 1: imagem de destaque do primeiro produto WooCommerce
// tenta via $produtos (já processados) e também direto em $products_meta (mais robusto)
if (!$hero_url) {
    $pids_para_hero = [];
    if (!empty($produtos)) {
        $pids_para_hero[] = (int)($produtos[0]['product_id'] ?? 0);
    }
    if (empty($pids_para_hero) && !empty($products_meta)) {
        $first_raw = reset($products_meta);
        if (is_array($first_raw) && isset($first_raw['product_id'])) {
            $pids_para_hero[] = (int)$first_raw['product_id'];
        }
    }
    foreach ($pids_para_hero as $fpid) {
        if (!$fpid) continue;
        // Tenta via thumbnail padrão (produtos simples)
        $thumb_id = get_post_thumbnail_id($fpid);
        // Se não encontrou, tenta via WC (necessário para product_variation)
        if (!$thumb_id && function_exists('wc_get_product')) {
            $wc_p = wc_get_product($fpid);
            if ($wc_p) {
                $thumb_id = $wc_p->get_image_id();
                // Se variação sem imagem própria, sobe para o produto pai
                if (!$thumb_id && $wc_p->is_type('variation')) {
                    $parent = wc_get_product($wc_p->get_parent_id());
                    if ($parent) $thumb_id = $parent->get_image_id();
                }
            }
        }
        if ($thumb_id) {
            $hero_url = (string) wp_get_attachment_image_url($thumb_id, 'full');
            break;
        }
    }
}
// Fallback 2: thumbnail do próprio post de proposta
if (!$hero_url && has_post_thumbnail($post_id)) {
    $hero_url = (string) get_the_post_thumbnail_url($post_id, 'full');
}

// Logo — branca para o hero da proposta (fundo escuro/foto)
$logo_url = pxo_logo_url();

// ── DEBUG ACF (visível apenas quando WP_DEBUG = true) ───────────────────
if ( defined('WP_DEBUG') && WP_DEBUG ) {
    echo '<details style="position:fixed;bottom:0;left:0;right:0;background:#1e1e1e;color:#9cdcfe;font:12px/1.5 monospace;padding:12px 16px;max-height:40vh;overflow:auto;z-index:99999;">';
    echo '<summary style="cursor:pointer;font-weight:bold;color:#4ec9b0;">🔍 PXO DEBUG — post_meta de ' . esc_html($post_id) . '</summary>';
    $all_meta = get_post_meta($post_id);
    echo '<table cellpadding="4" style="width:100%;border-collapse:collapse;">';
    echo '<tr><th style="text-align:left;border-bottom:1px solid #444">meta_key</th><th style="text-align:left;border-bottom:1px solid #444">meta_value (resumido)</th></tr>';
    foreach ($all_meta as $key => $values) {
        $val = maybe_unserialize($values[0]);
        $preview = is_array($val) ? wp_json_encode(array_slice($val, 0, 3)) : substr((string)$val, 0, 120);
        echo '<tr>';
        echo '<td style="border-bottom:1px solid #2a2a2a;padding:2px 8px">' . esc_html($key) . '</td>';
        echo '<td style="border-bottom:1px solid #2a2a2a;padding:2px 8px">' . esc_html($preview) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</details>';
}
// ────────────────────────────────────────────────────────────────────────

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($tour_title . ' | ' . get_bloginfo('name')); ?></title>
    <?php wp_head(); ?>

    <?php
    // CSS da proposta inline para garantir carga mesmo sem enqueue
    $proposta_css = get_template_directory() . '/assets/css/proposta.css';
    if (file_exists($proposta_css)) :
    ?>
    <style><?php echo file_get_contents($proposta_css); // phpcs:ignore ?></style>
    <?php endif; ?>
</head>
<body class="proposta-page">
<?php wp_body_open(); ?>

<!-- ── HERO (logo embutida, sem topbar branco) ───────── -->
<div class="p-hero<?php echo $hero_url ? '' : ' p-hero--fallback'; ?>"
     <?php if ($hero_url) echo 'style="background-image:url(' . esc_url($hero_url) . ');background-size:cover;background-position:center center;"'; ?>>  

    <?php if ( is_user_logged_in() && current_user_can( 'edit_post', $post_id ) ) :
        $edit_link = get_edit_post_link( $post_id ); ?>
    <a href="<?php echo esc_url( $edit_link ); ?>" class="p-topbar__edit p-hero__edit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        <span><?php echo esc_html( pxo_translate( 'pxo_edit_proposal', 'Editar proposta' ) ); ?></span>
    </a>
    <?php endif; ?>

    <?php if ($logo_url) : ?>
    <?php $logo_fallback = function_exists('pxo_logo_fallback_url') ? pxo_logo_fallback_url() : ''; ?>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="p-hero__logo">
        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php bloginfo('name'); ?>"<?php echo $logo_fallback ? ' data-pxo-logo-fallback="' . esc_attr($logo_fallback) . '"' : ''; ?>>
    </a>
    <?php else : ?>
    <a href="<?php echo esc_url(home_url('/')); ?>" class="p-hero__logo p-hero__logo--text">
        <strong><?php bloginfo('name'); ?></strong>
    </a>
    <?php endif; ?>

    <div class="p-hero__inner">
        <h1 class="p-hero__title"><?php echo esc_html($tour_title); ?></h1>
    </div>
</div>

<!-- ── GRID PRINCIPAL ─────────────────────────────── -->
<div class="p-wrap">

    <!-- COLUNA ESQUERDA -->
    <main class="p-content">

        <?php if ($greeting || $offer_description) : ?>
        <section class="p-section p-intro">
            <?php if ($greeting) : ?>
                <p class="p-intro__greeting"><?php echo esc_html($greeting); ?></p>
            <?php endif; ?>
            <?php if ($offer_description) : ?>
                <div class="p-intro__desc"><?php echo wp_kses_post($offer_description); ?></div>
            <?php endif; ?>
            <?php if ($important_note) : ?>
                <div class="p-notice"><?php echo wp_kses_post($important_note); ?></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($proposta_expirada) : ?>
        <div class="p-notice p-notice--expired">
            <?php echo esc_html( pxo_translate( 'pxo_proposal_expired_banner', 'Sua proposta expirou.' ) ); ?>
            <?php if ($agent['whatsapp']) :
                $wa = preg_replace('/\D/', '', $agent['whatsapp']); ?>
                <a href="https://wa.me/<?php echo $wa; ?>?text=<?php echo rawurlencode( pxo_translate( 'pxo_wa_help_text', 'I need help with a proposal' ) ); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html( pxo_translate( 'pxo_contact_agent_link', 'Fale com o agente para mais informações.' ) ); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($produtos)) : ?>
        <section class="p-section p-produtos">
            <!-- Banner Offer Updated -->
            <div id="p-offer-updated" class="p-offer-updated" aria-live="polite">
                <span class="p-offer-updated__icon">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </span>
                <?php echo esc_html( pxo_translate( 'pxo_offer_updated', 'Offer Updated' ) ); ?>
            </div>

            <?php foreach ($produtos as $p) :
                $sub = (float)$p['price'] * (int)$p['quantity'];
            ?>
            <div class="p-produto" data-product-index="<?php echo $p['n']; ?>">
                <div class="p-produto__box">
                <!-- Card dos dois blocos -->
                <div class="p-produto__card">
                    <!-- Bloco esquerdo: nome + data -->
                    <div class="p-produto__info">
                        <?php if ($p['nome']) : ?>
                            <h2 class="p-produto__name"><?php echo esc_html($p['nome']); ?></h2>
                        <?php endif; ?>
                        <?php if ($p['data_viagem']) : ?>
                            <p class="p-produto__date">
                                <strong><?php echo esc_html( pxo_translate( 'pxo_start_date', 'Start Date:' ) ); ?></strong>
                                <?php echo esc_html(pxo_format_date($p['data_viagem'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <!-- Bloco direito: controles qty + preço -->
                    <?php if ($p['price'] > 0) : ?>
                    <div class="p-produto__controls">
                        <div class="p-ctrl-row">
                            <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_quantity_label', 'Quantity' ) ); ?></span>
                            <div class="p-product-qty">
                                <input type="number"
                                       class="p-product-qty-input"
                                       value="<?php echo intval($p['quantity']); ?>"
                                       min="0"
                                       data-price="<?php echo floatval($p['price']); ?>"
                                       data-index="<?php echo $p['n']; ?>">
                                <button type="button"
                                        class="p-product-qty__remove"
                                        data-index="<?php echo $p['n']; ?>"
                                        title="<?php echo esc_attr( pxo_translate( 'pxo_remove_item', 'Remover item' ) ); ?>"
                                        aria-label="<?php echo esc_attr( pxo_translate( 'pxo_remove_item', 'Remover item' ) ); ?>">&#x2715;</button>
                            </div>
                        </div>
                        <div class="p-ctrl-row">
                            <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_price_per_person', 'Price per Person' ) ); ?></span>
                            <span class="p-ctrl-value"><?php echo pxo_format_brl($p['price']); ?></span>
                        </div>
                        <div class="p-ctrl-row p-ctrl-row--total">
                            <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_total_label', 'Total' ) ); ?></span>
                            <span class="p-ctrl-value p-total-inline" data-index="<?php echo $p['n']; ?>"><?php echo pxo_format_brl($sub); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div><!-- .p-produto__card -->

                <?php if ($p['descricao']) : ?>
                    <div class="p-produto__desc"><?php echo wp_kses_post($p['descricao']); ?></div>
                <?php endif; ?>
                </div><!-- .p-produto__box -->
            </div>
            <?php endforeach; ?>
        </section>
        <?php elseif ($product_name) : ?>
        <section class="p-section p-produtos">
            <div id="p-offer-updated" class="p-offer-updated" aria-live="polite">
                <span class="p-offer-updated__icon">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </span>
                <?php echo esc_html( pxo_translate( 'pxo_offer_updated', 'Offer Updated' ) ); ?>
            </div>
            <div class="p-produto" data-product-index="1">
                <div class="p-produto__box">
                <div class="p-produto__card">
                <div class="p-produto__info">
                    <h2 class="p-produto__name"><?php echo esc_html($product_name); ?></h2>
                    <?php if ($start_date) : ?>
                        <p class="p-produto__date"><strong><?php echo esc_html( pxo_translate( 'pxo_start_date', 'Start Date:' ) ); ?></strong> <?php echo esc_html(pxo_format_date($start_date)); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($price_per_person) : ?>
                <div class="p-produto__controls">
                    <div class="p-ctrl-row">
                        <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_quantity_label', 'Quantity' ) ); ?></span>
                        <div class="p-product-qty">
                            <input type="number"
                                   class="p-product-qty-input"
                                   value="<?php echo intval($quantity); ?>"
                                   min="0"
                                   data-price="<?php echo floatval($price_per_person); ?>"
                                   data-index="1">
                            <button type="button"
                                    class="p-product-qty__remove"
                                    data-index="1"
                                    title="<?php echo esc_attr( pxo_translate( 'pxo_remove_item', 'Remover item' ) ); ?>"
                                    aria-label="<?php echo esc_attr( pxo_translate( 'pxo_remove_item', 'Remover item' ) ); ?>">&#x2715;</button>
                        </div>
                    </div>
                    <div class="p-ctrl-row">
                        <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_price_per_person', 'Price per Person' ) ); ?></span>
                        <span class="p-ctrl-value"><?php echo pxo_format_brl($price_per_person); ?></span>
                    </div>
                    <div class="p-ctrl-row p-ctrl-row--total">
                        <span class="p-ctrl-label"><?php echo esc_html( pxo_translate( 'pxo_total_label', 'Total' ) ); ?></span>
                        <span class="p-ctrl-value p-total-inline" data-index="1"><?php echo pxo_format_brl($total_price); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                </div><!-- .p-produto__card -->
                </div><!-- .p-produto__box -->
            </div>
        </section>
        <?php endif; ?>

        <?php
        $has_itinerary = have_rows('itinerary_days', $post_id);
        $has_inc = have_rows('what_included', $post_id);
        $has_exc = have_rows('what_not_included', $post_id);
        if ($has_itinerary || $has_inc || $has_exc || $cancellation_policy) : ?>
        <div class="p-content__boxes">
        <?php endif; ?>
        <?php if ($has_itinerary) : ?>
        <section class="p-section p-itinerary">
            <h3 class="p-section__title"><?php echo esc_html( pxo_translate( 'pxo_itinerary', 'Itinerary' ) ); ?></h3>
            <?php $day_n = 1; while (have_rows('itinerary_days', $post_id)) : the_row();
                $d_num   = get_sub_field('day_number')      ?: sprintf( pxo_translate( 'pxo_day_n', 'Day %d' ), $day_n );
                $d_title = get_sub_field('day_title')       ?: '';
                $d_desc  = get_sub_field('day_description') ?: '';
            ?>
            <div class="p-day">
                <h4 class="p-day__title"><?php echo esc_html($d_num); ?><?php if ($d_title) echo ': ' . esc_html($d_title); ?></h4>
                <div class="p-day__desc"><?php echo wp_kses_post($d_desc); ?></div>
            </div>
            <?php $day_n++; endwhile; ?>
        </section>
        <?php endif; ?>

        <?php
        if ($has_inc || $has_exc) :
        ?>
        <section class="p-section p-inclusions">
            <div class="p-inclusions__grid">
                <?php if ($has_inc) : ?>
                <div class="p-inclusions__col p-inclusions__col--yes">
                    <h3 class="p-section__title"><?php echo esc_html( pxo_translate( 'pxo_what_included', 'What is included:' ) ); ?></h3>
                    <ul>
                        <?php while (have_rows('what_included', $post_id)) : the_row(); ?>
                            <li><span>✓</span><?php echo esc_html(get_sub_field('item')); ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php endif; ?>
                <?php if ($has_exc) : ?>
                <div class="p-inclusions__col p-inclusions__col--no">
                    <h3 class="p-section__title"><?php echo esc_html( pxo_translate( 'pxo_what_not_included', 'What is not included:' ) ); ?></h3>
                    <ul>
                        <?php while (have_rows('what_not_included', $post_id)) : the_row(); ?>
                            <li><span>✗</span><?php echo esc_html(get_sub_field('item')); ?></li>
                        <?php endwhile; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($cancellation_policy) : ?>
        <section class="p-section p-cancel">
            <h3 class="p-section__title"><?php echo esc_html( pxo_translate( 'pxo_cancellation_title', 'Cancellation policy:' ) ); ?></h3>
            <div class="p-cancel__body"><?php echo wp_kses_post($cancellation_policy); ?></div>
        </section>
        <?php endif; ?>
        <?php if ($has_itinerary || $has_inc || $has_exc || $cancellation_policy) : ?>
        </div><!-- .p-content__boxes -->
        <?php endif; ?>

        <section class="p-section p-coupon">
            <div class="p-coupon__row">
                <input type="text" id="coupon-input" placeholder="<?php echo esc_attr( pxo_translate( 'pxo_coupon_placeholder', 'Coupon code' ) ); ?>">
                <button type="button" id="coupon-btn"><?php echo esc_html( pxo_translate( 'pxo_apply_coupon', 'Apply Coupon' ) ); ?></button>
            </div>
            <p id="coupon-msg" class="p-coupon__msg" hidden></p>
        </section>

    </main>

    <!-- SIDEBAR DIREITA -->
    <aside class="p-sidebar">

        <!-- Card único: total + botão + agente -->
        <div class="p-card p-sidebar-card">

            <!-- Booking total -->
            <p class="p-booking__label"><?php echo esc_html( pxo_translate( 'pxo_booking_total', 'Booking Total' ) ); ?></p>
            <div class="p-booking__total" id="sidebar-total"><?php echo pxo_format_brl($total_price); ?></div>
            <button class="p-btn-book<?php echo $proposta_expirada ? ' p-btn-book--expired' : ''; ?>" id="btn-book"<?php echo $proposta_expirada ? ' disabled aria-disabled="true"' : ''; ?>>
                <?php echo $proposta_expirada ? esc_html( pxo_translate( 'pxo_proposal_expired_btn', 'Proposal Expired' ) ) : esc_html( pxo_translate( 'pxo_book_now', 'Book Now' ) ); ?>
            </button>

            <?php if ($agent['name']) : ?>
            <!-- Divisor -->
            <hr class="p-sidebar-divider">

            <!-- Agente -->
            <div class="p-agent">
                <?php if ($agent['photo']) : ?>
                    <img class="p-agent__photo" src="<?php echo esc_url($agent['photo']); ?>" alt="<?php echo esc_attr($agent['name']); ?>">
                <?php endif; ?>

                <p class="p-agent__help"><?php echo esc_html( pxo_translate( 'pxo_need_help_offer', 'Need help with your offer?' ) ); ?></p>
                <p class="p-agent__info"><?php
                    echo wp_kses_post(
                        sprintf(
                            pxo_translate( 'pxo_contact_advisor', 'Contact %s your travel advisor at PlanetaEXO' ),
                            '<strong>' . esc_html( $agent['name'] ) . '</strong>'
                        )
                    );
                ?></p>

                <div class="p-agent__actions">
                    <?php if ($agent['whatsapp']) :
                        $wa = preg_replace('/\D/', '', $agent['whatsapp']); ?>
                        <a href="https://wa.me/<?php echo $wa; ?>" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                            <?php echo esc_html( pxo_translate( 'pxo_whatsapp', 'WhatsApp' ) ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent['email']) : ?>
                        <a href="mailto:<?php echo esc_attr($agent['email']); ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <?php echo esc_html( pxo_translate( 'pxo_email', 'Email' ) ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($agent['schedule_link']) : ?>
                        <a href="<?php echo esc_url($agent['schedule_link']); ?>" target="_blank" rel="noopener">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?php echo esc_html( pxo_translate( 'pxo_schedule_call', 'Schedule a call' ) ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.p-sidebar-card -->

    </aside>
</div><!-- .p-wrap -->

<!-- ── BARRA FLUTUANTE MOBILE (Booking Total + Book Now) ── -->
<div class="p-mobile-bar" id="p-mobile-bar">
    <div class="p-mobile-bar__price">
        <p class="p-mobile-bar__label"><?php echo esc_html( pxo_translate( 'pxo_booking_total', 'Booking Total' ) ); ?></p>
        <div class="p-mobile-bar__total" id="mobile-sidebar-total"><?php echo pxo_format_brl($total_price); ?></div>
    </div>
    <button type="button" class="p-mobile-bar__btn<?php echo $proposta_expirada ? ' p-mobile-bar__btn--expired' : ''; ?>" id="btn-book-mobile"<?php echo $proposta_expirada ? ' disabled aria-disabled="true"' : ''; ?>>
        <?php echo $proposta_expirada ? esc_html( pxo_translate( 'pxo_proposal_expired_btn', 'Proposal Expired' ) ) : esc_html( pxo_translate( 'pxo_book_now', 'Book Now' ) ); ?>
    </button>
</div>

<!-- ── FOOTER ─────────────────────────────────────── -->
<footer class="p-footer">
    <div class="p-footer__inner">
        <div class="p-footer__left">© <?php echo date('Y'); ?> <?php echo esc_html( pxo_translate( 'pxo_footer_rights', 'PlanetaEXO. Todos os direitos reservados.' ) ); ?></div>
        <div class="p-footer__right">
            <svg viewBox="0 0 24 24" fill="#4caf50" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            <span><?php echo esc_html( pxo_translate( 'pxo_secure_payment', 'Secure payment' ) ); ?></span>
            <img src="https://bookings.planetaexo.com/wp-content/uploads/2026/03/secured_payments_planetaexo-2.webp" alt="<?php echo esc_attr( pxo_translate( 'pxo_payments_alt', 'Visa, Mastercard, Pix' ) ); ?>" class="p-footer__payments" width="150" height="55">
        </div>
    </div>
</footer>

<script>
(function () {
    var campaignSlug = <?php echo json_encode($post->post_name); ?>;
    var ajaxUrl      = <?php echo json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce        = <?php echo json_encode(wp_create_nonce('pxo_proposta')); ?>;
    var pxoI18n      = <?php echo wp_json_encode(
        array(
            'bookNow'       => pxo_translate( 'pxo_book_now', 'Book Now' ),
            'loading'       => '...',
            'selectProduct' => pxo_translate( 'pxo_js_select_product', 'Please select at least one product before continuing.' ),
            'errorProcess'  => pxo_translate( 'pxo_js_error_process', 'Error processing request:' ),
            'tryAgain'      => pxo_translate( 'pxo_js_try_again', 'Please try again.' ),
            'couponEmpty'   => pxo_translate( 'pxo_js_coupon_empty', 'Please enter a coupon code.' ),
            'couponApplied' => pxo_translate( 'pxo_js_coupon_applied', 'Coupon "%s" applied!' ),
        )
    ); ?>;
    var offerUpdatedTimer = null;

    function formatBRL(v) {
        return 'R$ ' + parseFloat(v).toFixed(2)
            .replace('.', ',')
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Recalcula grand-total somando todos os produtos
    function recalcTotal() {
        var grand = 0;
        document.querySelectorAll('.p-product-qty-input').forEach(function (inp) {
            var qty   = Math.max(0, parseInt(inp.value) || 0);
            var price = parseFloat(inp.dataset.price) || 0;
            var idx   = inp.dataset.index;
            var sub   = qty * price;
            grand += sub;

            // Atualiza total inline no card do produto (coluna esquerda)
            var totInline = document.querySelector('.p-total-inline[data-index="' + idx + '"]');
            if (totInline) totInline.textContent = formatBRL(sub);
        });

        // Atualiza total principal (sidebar + barra mobile)
        var totalEl = document.getElementById('sidebar-total');
        if (totalEl) totalEl.textContent = formatBRL(grand);
        var mobileTotal = document.getElementById('mobile-sidebar-total');
        if (mobileTotal) mobileTotal.textContent = formatBRL(grand);

        // Exibe banner "Offer Updated" por 3s
        var banner = document.getElementById('p-offer-updated');
        if (banner) {
            banner.classList.add('is-visible');
            clearTimeout(offerUpdatedTimer);
            offerUpdatedTimer = setTimeout(function () {
                banner.classList.remove('is-visible');
            }, 3000);
        }
    }

    // Inputs de quantidade
    document.querySelectorAll('.p-product-qty-input').forEach(function (inp) {
        inp.addEventListener('input', recalcTotal);
    });

    // Botão × — remove item (qty → 0)
    document.querySelectorAll('.p-product-qty__remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var idx = this.dataset.index;
            var inp = document.querySelector('.p-product-qty-input[data-index="' + idx + '"]');
            if (inp) { inp.value = 0; recalcTotal(); }
        });
    });

    // Book Now — atualiza quantidades via AJAX, adiciona ao carrinho e vai direto ao checkout
    var btnBook = document.getElementById('btn-book');
    var btnBookMobile = document.getElementById('btn-book-mobile');
    function handleBookNow() {
            var quantities = {};
            var hasAny = false;
            document.querySelectorAll('.p-product-qty-input').forEach(function (inp) {
                var qty = parseInt(inp.value) || 0;
                quantities[inp.dataset.index] = qty;
                if (qty > 0) hasAny = true;
            });

            if (!hasAny) {
                alert(pxoI18n.selectProduct);
                return;
            }

            // Desabilita botões para evitar duplo clique
            btnBook.disabled = true;
            btnBook.textContent = pxoI18n.loading;
            if (btnBookMobile) {
                btnBookMobile.disabled = true;
                btnBookMobile.textContent = pxoI18n.loading;
            }

            var data = new FormData();
            data.append('action',  'pxo_update_quantities');
            data.append('nonce',   nonce);
            data.append('post_id', <?php echo json_encode($post_id); ?>);
            Object.keys(quantities).forEach(function (k) {
                data.append('quantities[' + k + ']', quantities[k]);
            });

            fetch(ajaxUrl, {
                method: 'POST',
                body:   data,
                credentials: 'same-origin'
            })
            .then(function (r) { return r.json(); })
            .then(function (resp) {
                if (resp.success) {
                    // O AJAX retorna uma URL com token (?pxo_cart=TOKEN)
                    // que em template_redirect monta o carrinho e redireciona ao checkout
                    if (resp.data && resp.data.redirect) {
                        window.location.href = resp.data.redirect;
                    } else {
                        // Fallback legado
                        window.location.href = '/' + campaignSlug + '/?book=1';
                    }
                } else {
                    var errMsg = resp.data && resp.data.message ? resp.data.message : pxoI18n.tryAgain;
                    alert(pxoI18n.errorProcess + ' ' + errMsg);
                    btnBook.disabled = false;
                    btnBook.textContent = pxoI18n.bookNow;
                    if (btnBookMobile) {
                        btnBookMobile.disabled = false;
                        btnBookMobile.textContent = pxoI18n.bookNow;
                    }
                }
            })
            .catch(function () {
                // Fallback de rede: usa fluxo antigo
                window.location.href = '/' + campaignSlug + '/?book=1';
            });
    }
    if (btnBook) {
        btnBook.addEventListener('click', handleBookNow);
    }
    if (btnBookMobile) {
        btnBookMobile.addEventListener('click', handleBookNow);
    }

    // Cupom
    var couponBtn = document.getElementById('coupon-btn');
    if (couponBtn) {
        couponBtn.addEventListener('click', function () {
            var code = document.getElementById('coupon-input').value.trim();
            var msg  = document.getElementById('coupon-msg');
            if (!code) {
                msg.hidden = false;
                msg.style.color = '#c0392b';
                msg.textContent = pxoI18n.couponEmpty;
                return;
            }
            // TODO: validação via AJAX
            msg.hidden = false;
            msg.style.color = '#27ae60';
            msg.textContent = pxoI18n.couponApplied.replace('%s', code);
        });
    }
})();
</script>

<?php
// Debug de mapeamento produto↔campo ACF — visível apenas para admins com ?pxo_debug=1
if ( class_exists('PlanetaExoMappingDebug') ) {
    PlanetaExoMappingDebug::maybe_render( $post_id );
}
?>

<?php wp_footer(); ?>
</body>
</html>
