<?php
/**
 * Template para exibir Proposta no Frontend
 * 
 * Estrutura:
 * - Header hero com imagem de fundo
 * - 2 colunas: conteúdo esquerdo + sidebar direita (sticky)
 * - Itinerário multi-dia
 * - Preço, inclusões, política de cancelamento
 * - Agente de viagem
 */

get_header();

// Obtém valores dos campos ACF
$tour_title = get_field('tour_title');
$hero_image = get_field('hero_image');
$guest_names = get_field('guest_names');
$greeting = get_field('greeting');
$offer_description = get_field('offer_description');
$important_note = get_field('important_note');
$product_name = get_field('product_name');
$start_date = get_field('start_date');
$quantity = get_field('quantity') ?: 1;
$price_per_person = floatval(get_field('price_per_person')) ?: 0;
$total_price = floatval(get_field('total_price')) ?: ($quantity * $price_per_person);
$cancellation_policy = get_field('cancellation_policy');
$coupon_code = get_field('coupon_code');

// Agente (pode vir de relacionamento ou direto)
$agent_name = get_field('agent_name');
$agent_photo = get_field('agent_photo');
$agent_whatsapp = get_field('agent_whatsapp');
$agent_email = get_field('agent_email') ?: get_the_author_meta('user_email', get_post_field('post_author'));
$agent_phone = get_field('agent_phone');

?>

<style>
    /* Variables */
    :root {
        --color-primary: #26c6da;
        --color-dark: #333;
        --color-light: #555;
        --color-border: #ddd;
        --color-bg: #f5f5f5;
        --spacing-lg: 40px;
        --spacing-md: 20px;
        --spacing-sm: 10px;
    }

    /* Reset */
    .proposal-wrapper {
        margin: 0;
        padding: 0;
    }

    /* Hero Header */
    .proposal-hero {
        position: relative;
        height: 400px;
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        text-align: center;
        overflow: hidden;
    }

    .proposal-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.3);
        z-index: 1;
    }

    .proposal-hero-content {
        position: relative;
        z-index: 2;
        max-width: 90%;
    }

    .proposal-hero h1 {
        font-size: 3rem;
        font-weight: bold;
        margin: 0 0 10px 0;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .proposal-hero p {
        font-size: 1.2rem;
        margin: 0;
        opacity: 0.9;
    }

    /* Main Container - 2 Colunas */
    .proposal-container {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: var(--spacing-lg);
        max-width: 1400px;
        margin: 0 auto;
        padding: var(--spacing-lg) var(--spacing-md);
        background: white;
    }

    .proposal-content {
        order: 1;
    }

    .proposal-sidebar {
        order: 2;
    }

    /* Section Base */
    .proposal-section {
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-lg);
        border-bottom: 1px solid var(--color-border);
    }

    .proposal-section:last-of-type {
        border-bottom: none;
    }

    .proposal-section h2 {
        font-size: 1.8rem;
        font-weight: bold;
        margin: 0 0 15px 0;
        color: var(--color-dark);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .proposal-section h3 {
        font-size: 1.4rem;
        font-weight: bold;
        margin: 0 0 15px 0;
        color: var(--color-dark);
        text-transform: uppercase;
    }

    .proposal-section p {
        margin: 0 0 15px 0;
        line-height: 1.6;
        color: var(--color-light);
    }

    .proposal-section ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .proposal-section li {
        padding: 8px 0 8px 30px;
        position: relative;
        line-height: 1.6;
        color: var(--color-light);
    }

    .proposal-section li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #4CAF50;
        font-weight: bold;
        font-size: 1.2rem;
    }

    .what-not-included li::before {
        content: '✗';
        color: #f44336;
    }

    /* Introduction */
    .introduction p.greeting {
        font-weight: bold;
        margin-bottom: var(--spacing-md);
    }

    .introduction p.important-note {
        background: #fff3cd;
        padding: var(--spacing-md);
        border-left: 4px solid #ffc107;
        margin: var(--spacing-md) 0;
        font-weight: 500;
    }

    /* Product Info */
    .product-info p {
        font-size: 1.1rem;
        margin: 5px 0;
    }

    /* Itinerary */
    .itinerary-day {
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-lg);
        border-bottom: 1px solid var(--color-border);
    }

    .itinerary-day:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .itinerary-day h4 {
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--color-dark);
        margin: 0 0 10px 0;
    }

    .day-description {
        line-height: 1.8;
        color: var(--color-light);
    }

    .day-description p {
        margin: 10px 0;
    }

    /* Inclusions Grid */
    .inclusions-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--spacing-lg);
        margin-top: var(--spacing-md);
    }

    .what-included,
    .what-not-included {
        padding: var(--spacing-md);
        background: var(--color-bg);
        border-radius: 5px;
    }

    /* Cancellation Policy */
    .cancellation-policy {
        background: var(--color-bg);
        padding: var(--spacing-md);
        border-radius: 5px;
    }

    .cancellation-policy ul {
        margin-left: 20px;
    }

    /* Coupon */
    .coupon-section {
        display: flex;
        gap: 10px;
        margin-top: var(--spacing-md);
        border: 1px solid var(--color-border);
        padding: var(--spacing-md);
        border-radius: 5px;
        background: white;
    }

    .coupon-section input {
        flex: 1;
        padding: 10px;
        border: 1px solid var(--color-border);
        border-radius: 3px;
        font-size: 1rem;
    }

    .coupon-section button {
        padding: 10px 20px;
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: 3px;
        font-weight: bold;
        cursor: pointer;
        transition: background 0.3s;
    }

    .coupon-section button:hover {
        background: #1aa8b8;
    }

    /* SIDEBAR */
    .proposal-sidebar {
        position: sticky;
        top: 20px;
        height: fit-content;
    }

    /* Price Box */
    .price-box {
        background: white;
        padding: var(--spacing-lg);
        border-radius: 5px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: var(--spacing-lg);
        text-align: center;
    }

    .price-box h3 {
        margin: 0 0 15px 0;
        font-size: 1.3rem;
        text-transform: none;
        text-align: center;
    }

    .price-display {
        font-size: 2.2rem;
        font-weight: bold;
        color: var(--color-dark);
        margin: 20px 0;
    }

    .price-breakdown {
        border-top: 1px solid var(--color-border);
        padding-top: 15px;
        margin-top: 15px;
        text-align: left;
        font-size: 0.95rem;
    }

    .price-breakdown-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        color: var(--color-light);
    }

    .price-breakdown-total {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid var(--color-border);
        font-weight: bold;
        color: var(--color-dark);
    }

    .price-controls {
        margin: var(--spacing-md) 0;
    }

    .quantity-control {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .quantity-control label {
        font-size: 0.95rem;
        color: var(--color-light);
    }

    .quantity-control input {
        width: 60px;
        padding: 8px;
        border: 1px solid var(--color-border);
        border-radius: 3px;
        text-align: center;
        font-size: 1rem;
    }

    .book-now-btn {
        width: 100%;
        padding: 15px;
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: 3px;
        font-weight: bold;
        font-size: 1.1rem;
        cursor: pointer;
        transition: background 0.3s;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .book-now-btn:hover {
        background: #1aa8b8;
    }

    /* Agent Box */
    .agent-box {
        background: white;
        padding: var(--spacing-lg);
        border-radius: 5px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .agent-avatar {
        margin-bottom: 15px;
    }

    .agent-avatar img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--color-primary);
    }

    .agent-name {
        font-size: 1.2rem;
        font-weight: bold;
        color: var(--color-dark);
        margin: 15px 0 5px 0;
    }

    .agent-question {
        font-size: 1rem;
        color: var(--color-light);
        margin: 10px 0;
        font-weight: 500;
    }

    .agent-contact {
        font-size: 0.95rem;
        color: var(--color-light);
        margin: 10px 0 15px 0;
        line-height: 1.5;
    }

    .agent-contact strong {
        color: var(--color-dark);
    }

    .agent-links {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 15px;
    }

    .agent-links a {
        display: block;
        padding: 12px;
        color: var(--color-primary);
        text-decoration: none;
        border: 2px solid var(--color-primary);
        border-radius: 3px;
        transition: all 0.3s;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .agent-links a:hover {
        background: var(--color-primary);
        color: white;
    }

    /* Responsivo */
    @media (max-width: 1024px) {
        .proposal-container {
            grid-template-columns: 1fr;
            padding: var(--spacing-md);
            gap: var(--spacing-md);
        }

        .proposal-hero h1 {
            font-size: 2rem;
        }

        .inclusions-grid {
            grid-template-columns: 1fr;
        }

        .proposal-sidebar {
            position: static;
            order: 3;
        }
    }

    @media (max-width: 768px) {
        .proposal-hero {
            height: 250px;
        }

        .proposal-hero h1 {
            font-size: 1.5rem;
        }

        .proposal-section h2 {
            font-size: 1.3rem;
        }

        .price-display {
            font-size: 1.8rem;
        }

        .coupon-section {
            flex-direction: column;
        }

        .coupon-section input,
        .coupon-section button {
            width: 100%;
        }
    }
</style>

<div class="proposal-wrapper">

    <!-- HEADER HERO -->
    <?php if ($hero_image) : 
        $image_url = is_array($hero_image) ? $hero_image['url'] : $hero_image;
        $image_style = "background-image: url('$image_url');";
    else :
        $image_style = "background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);";
    endif;
    ?>
    <div class="proposal-hero" style="<?php echo $image_style; ?>">
        <div class="proposal-hero-content">
            <h1><?php echo $tour_title ?: 'Travel Offer'; ?></h1>
            <?php if ($guest_names) : ?>
                <p><?php echo 'Dear ' . $guest_names; ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTAINER: 2 Colunas -->
    <div class="proposal-container">

        <!-- LEFT COLUMN: Conteúdo Principal -->
        <main class="proposal-content">

            <!-- INTRODUCTION SECTION -->
            <section class="proposal-section introduction">
                <p class="greeting"><?php echo $greeting ?: 'Dear Guest,'; ?></p>
                <p><?php echo wpautop($offer_description); ?></p>
                <?php if ($important_note) : ?>
                    <p class="important-note"><strong><?php echo $important_note; ?></strong></p>
                <?php endif; ?>
            </section>

            <!-- PRODUCT INFO SECTION -->
            <section class="proposal-section product-info">
                <h2><?php echo $product_name ?: 'Tour Package'; ?></h2>
                <?php if ($start_date) : ?>
                    <p><strong>Start Date:</strong> <?php echo date('d/m/Y', strtotime($start_date)); ?></p>
                <?php endif; ?>
            </section>

            <!-- ITINERARY SECTION -->
            <?php if (have_rows('itinerary_days')) : ?>
                <section class="proposal-section itinerary">
                    <h3>Itinerary</h3>
                    <?php 
                    $day_count = 1;
                    while (have_rows('itinerary_days')) : the_row();
                        $day_number = get_sub_field('day_number') ?: 'Day ' . $day_count;
                        $day_title = get_sub_field('day_title');
                        $day_description = get_sub_field('day_description');
                        ?>
                        <div class="itinerary-day">
                            <h4><?php echo $day_number; ?>: <?php echo $day_title; ?></h4>
                            <div class="day-description">
                                <?php echo wp_kses_post($day_description); ?>
                            </div>
                        </div>
                        <?php
                        $day_count++;
                    endwhile;
                    ?>
                </section>
            <?php endif; ?>

            <!-- PRICE BREAKDOWN (Mobile - aparece aqui também) -->
            <section class="proposal-section price-info-mobile" style="display: none;">
                <h3>Pricing</h3>
                <div class="price-breakdown">
                    <div class="price-breakdown-row">
                        <span>Price per Person:</span>
                        <span>R$ <?php echo number_format($price_per_person, 2, ',', '.'); ?></span>
                    </div>
                    <div class="price-breakdown-row">
                        <span>Quantity:</span>
                        <span><?php echo $quantity; ?> person(s)</span>
                    </div>
                    <div class="price-breakdown-total">
                        <span>Total:</span>
                        <span>R$ <?php echo number_format($total_price, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </section>

            <!-- INCLUSIONS SECTION -->
            <?php 
            $has_included = have_rows('what_included');
            $has_not_included = have_rows('what_not_included');
            if ($has_included || $has_not_included) :
            ?>
                <section class="proposal-section inclusions">
                    <div class="inclusions-grid">
                        <?php if ($has_included) : ?>
                            <div class="what-included">
                                <h3>What is included:</h3>
                                <ul>
                                    <?php 
                                    while (have_rows('what_included')) : the_row();
                                        $item = get_sub_field('item');
                                        echo '<li>' . $item . '</li>';
                                    endwhile;
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ($has_not_included) : ?>
                            <div class="what-not-included">
                                <h3>What is not included:</h3>
                                <ul>
                                    <?php 
                                    while (have_rows('what_not_included')) : the_row();
                                        $item = get_sub_field('item');
                                        echo '<li>' . $item . '</li>';
                                    endwhile;
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- CANCELLATION POLICY SECTION -->
            <?php if ($cancellation_policy) : ?>
                <section class="proposal-section cancellation-policy">
                    <h3>Cancellation Policy</h3>
                    <div class="policy-content">
                        <?php echo wp_kses_post($cancellation_policy); ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- COUPON SECTION -->
            <section class="proposal-section coupon">
                <h3>Apply Coupon Code</h3>
                <div class="coupon-section">
                    <input type="text" class="coupon-input" placeholder="Coupon code" value="">
                    <button class="coupon-button" onclick="applyCoupon()">APPLY COUPON</button>
                </div>
                <div class="coupon-message" style="margin-top: 10px; display: none; color: #4CAF50; font-weight: bold;"></div>
            </section>

        </main>

        <!-- RIGHT SIDEBAR: Preço + Agente -->
        <aside class="proposal-sidebar">

            <!-- PRICE BOX (Sticky) -->
            <div class="price-box">
                <h3>Booking Total</h3>

                <div class="price-controls">
                    <div class="quantity-control">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" value="<?php echo $quantity; ?>" min="1" onchange="updatePrice()">
                    </div>
                </div>

                <div class="price-breakdown">
                    <div class="price-breakdown-row">
                        <span>Price per Person:</span>
                        <span id="price-per-person">R$ <?php echo number_format($price_per_person, 2, ',', '.'); ?></span>
                    </div>
                    <div class="price-breakdown-row">
                        <span id="qty-label">Quantity: <span id="qty-value"><?php echo $quantity; ?></span></span>
                    </div>
                    <div class="price-breakdown-total">
                        <span>Total:</span>
                        <span id="total-price">R$ <?php echo number_format($total_price, 2, ',', '.'); ?></span>
                    </div>
                </div>

                <button class="book-now-btn" onclick="bookNow()">BOOK NOW</button>
            </div>

            <!-- AGENT BOX -->
            <?php if ($agent_name) : ?>
                <div class="agent-box">
                    <?php if ($agent_photo) : 
                        echo wp_get_attachment_image($agent_photo, 'medium', false, array('class' => 'agent-avatar'));
                    else :
                        if ($agent_photo) echo wp_get_attachment_image($agent_photo, 'thumbnail');
                    endif;
                    ?>
                    <p class="agent-name"><?php echo esc_html( $agent_name ); ?></p>
                    <p class="agent-question"><?php echo esc_html( planetaexo_t( 'pxo_need_help_offer', 'Need help with your offer?' ) ); ?></p>
                    <p class="agent-contact"><?php
						echo wp_kses_post(
							sprintf(
								planetaexo_t( 'pxo_contact_advisor', 'Contact %s your travel advisor at PlanetaEXO' ),
								'<strong>' . esc_html( $agent_name ) . '</strong>'
							)
						);
                    ?></p>

                    <div class="agent-links">
                        <?php if ($agent_whatsapp) :
                            $whatsapp_number = preg_replace('/\D/', '', $agent_whatsapp);
                            ?>
                            <a href="https://wa.me/<?php echo esc_attr( $whatsapp_number ); ?>" target="_blank" rel="noopener"><?php echo esc_html( planetaexo_t( 'pxo_whatsapp', 'WhatsApp' ) ); ?></a>
                        <?php endif; ?>

                        <?php if ($agent_email) : ?>
                            <a href="mailto:<?php echo esc_attr( $agent_email ); ?>"><?php echo esc_html( planetaexo_t( 'pxo_email', 'Email' ) ); ?></a>
                        <?php endif; ?>

                        <a href="#schedule-call"><?php echo esc_html( planetaexo_t( 'pxo_schedule_call', 'Schedule a call' ) ); ?></a>
                    </div>
                </div>
            <?php endif; ?>

        </aside>

    </div>

</div>

<script>
    // Função para atualizar o preço quando quantidade muda
    function updatePrice() {
        const quantity = parseInt(document.getElementById('quantity').value) || 1;
        const pricePerPerson = <?php echo $price_per_person; ?>;
        const total = quantity * pricePerPerson;

        document.getElementById('qty-value').textContent = quantity;
        document.getElementById('total-price').textContent = 'R$ ' + formatCurrency(total);
    }

    // Função para formatar moeda
    function formatCurrency(value) {
        return value.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // Função para aplicar cupom
    function applyCoupon() {
        const couponCode = document.querySelector('.coupon-input').value;
        const message = document.querySelector('.coupon-message');

        if (!couponCode) {
            message.style.display = 'block';
            message.style.color = '#f44336';
            message.textContent = 'Please enter a coupon code';
            return;
        }

        // Aqui você faria uma chamada AJAX para validar o cupom
        message.style.display = 'block';
        message.style.color = '#4CAF50';
        message.textContent = 'Coupon applied successfully!';
    }

    // Função para fazer booking
    function bookNow() {
        const quantity = document.getElementById('quantity').value;
        const total = document.getElementById('total-price').textContent;

        // Redirecionar para página de checkout ou abrir modal
        // Este é um exemplo - você precisa conectar isso com seu sistema de checkout
        alert('Redirecting to checkout...\nQuantity: ' + quantity + '\nTotal: ' + total);
        // window.location.href = '/checkout/?proposal_id=<?php echo get_the_ID(); ?>&quantity=' + quantity;
    }
</script>

<?php get_footer(); ?>
