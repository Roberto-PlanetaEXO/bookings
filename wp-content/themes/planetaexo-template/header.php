<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="site-header__inner container">

        <a class="site-logo" href="<?php echo esc_url(home_url('/')); ?>">
            <?php
            $logo = pxo_logo_url();
            $fallback = function_exists('pxo_logo_fallback_url') ? pxo_logo_fallback_url() : '';
            if ($logo) :
                $attr = $fallback ? ' data-pxo-logo-fallback="' . esc_attr($fallback) . '"' : '';
                echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '"' . $attr . '>';
            else :
                echo '<span class="site-logo__text">' . get_bloginfo('name') . '</span>';
            endif;
            ?>
        </a>

        <nav class="site-nav" aria-label="<?php esc_attr_e('Menu principal', 'planetaexo'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'menu_class'     => 'site-nav__list',
                'container'      => false,
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>

        <button class="site-nav__toggle" aria-expanded="false" aria-controls="site-nav-list" aria-label="Abrir menu">
            <span></span><span></span><span></span>
        </button>

    </div>
</header>

<div id="page-content">
