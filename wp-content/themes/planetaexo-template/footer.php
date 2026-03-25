</div><!-- #page-content -->

<?php if ( function_exists('is_checkout') && is_checkout() ) : ?>
<!-- ── FOOTER MINIMAL (checkout) ──────────────────────── -->
<footer class="checkout-footer" role="contentinfo">
    <div class="checkout-footer__inner">

        <a class="checkout-footer__logo" href="<?php echo esc_url( home_url('/') ); ?>" aria-label="<?php echo esc_attr( get_bloginfo('name') ); ?>">
            <?php
            $logo = pxo_logo_url();
            $fallback = function_exists('pxo_logo_fallback_url') ? pxo_logo_fallback_url() : '';
            if ( $logo ) {
                $attr = $fallback ? ' data-pxo-logo-fallback="' . esc_attr($fallback) . '"' : '';
                echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr( get_bloginfo('name') ) . '"' . $attr . '>';
            } else {
                echo '<strong>' . esc_html( get_bloginfo('name') ) . '</strong>';
            }
            ?>
        </a>

        <div class="checkout-footer__badges">
            <!-- Compra segura -->
            <span class="cf-badge">
                <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M10 2L3 5v5c0 4.418 3.05 8.55 7 9.93C14.95 18.55 18 14.418 18 10V5L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <path d="M7 10l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Compra segura
            </span>
            <!-- SSL -->
            <span class="cf-badge">
                <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="3" y="9" width="14" height="9" rx="2" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M7 9V6a3 3 0 0 1 6 0v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="10" cy="13.5" r="1" fill="currentColor"/>
                </svg>
                SSL / Dados protegidos
            </span>
            <!-- PIX -->
            <span class="cf-badge">
                <svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M7 10h6M10 7v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Pagamento via PIX
            </span>
            <!-- Logos de pagamento (Visa, Mastercard, Pix) -->
            <img src="https://bookings.planetaexo.com/wp-content/uploads/2026/03/secured_payments_planetaexo-2.webp" alt="Visa, Mastercard, Pix" class="checkout-footer__payments-img" width="150" height="55">
        </div>

        <p class="checkout-footer__copy">
            © <?php echo date('Y'); ?> <?php echo esc_html( get_bloginfo('name') === 'Bookings-PlanetaExo' ? 'Bookings - PlanetaEXO' : get_bloginfo('name') ); ?>. Todos os direitos reservados.
        </p>

    </div>
</footer>

<?php else : ?>
<!-- ── FOOTER COMPLETO (demais páginas) ──────────────── -->
<footer class="site-footer">
    <div class="site-footer__inner container">

        <div class="site-footer__brand">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <?php
                $logo = pxo_logo_url();
                $fallback = function_exists('pxo_logo_fallback_url') ? pxo_logo_fallback_url() : '';
                if ($logo) {
                    $attr = $fallback ? ' data-pxo-logo-fallback="' . esc_attr($fallback) . '"' : '';
                    echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '" class="footer-logo"' . $attr . '>';
                }
                ?>
            </a>
            <p><?php bloginfo('description'); ?></p>
        </div>

        <nav class="site-footer__nav" aria-label="Menu rodapé">
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'menu_class'     => 'footer-nav__list',
                'container'      => false,
                'fallback_cb'    => false,
                'depth'          => 1,
            ]);
            ?>
        </nav>

    </div>

    <div class="site-footer__bottom">
        <div class="container">
            <span>© <?php echo date('Y'); ?> <?php echo esc_html( get_bloginfo('name') === 'Bookings-PlanetaExo' ? 'Bookings - PlanetaEXO' : get_bloginfo('name') ); ?>. Todos os direitos reservados.</span>
        </div>
    </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
