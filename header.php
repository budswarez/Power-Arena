<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

/**
 * Starts the document: doctype/html/head (wp_head()) through the opening
 * <body> tag and the site header itself (top bar, branding, main mega
 * menu, search toggle). Reproduces the live site's header shape
 * (`#header.site-header.header-style-2.full-width`) clean-room — the
 * actual markup below was written from the spec, not copied from the
 * legacy Publisher theme's PHP.
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="header" class="site-header header-style-2 full-width">
    <?php get_template_part('template-parts/header/topbar'); ?>

    <div class="header-main">
        <?php get_template_part('template-parts/header/branding'); ?>
    </div>

    <div class="main-menu-bar">
        <div class="main-menu-bar__inner container">
            <button
                type="button"
                class="mobile-menu-toggle"
                aria-expanded="false"
                aria-controls="offcanvas-menu"
                aria-label="<?php esc_attr_e('Abrir menu', 'arena'); ?>"
            >
                <span class="screen-reader-text"><?php esc_html_e('Abrir menu', 'arena'); ?></span>
                <span class="mobile-menu-toggle__lines" aria-hidden="true"></span>
            </button>

            <nav class="main-menu-container" aria-label="<?php esc_attr_e('Menu principal', 'arena'); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'main-menu',
                    'container'      => false,
                    'menu_id'        => 'main-navigation',
                    'menu_class'     => 'main-menu menu bsm-pure clearfix',
                    'walker'         => new \Arena\Menus\MegaMenuWalker(),
                    'fallback_cb'    => false,
                ]);
                ?>
            </nav>

            <?php get_template_part('template-parts/header/search'); ?>
        </div>
    </div>

    <!-- Reserves the bar's own height once JS pins it (position:fixed) so
         the page content below doesn't jump up — see initSmartStickyMenuBar()
         in main.js. Empty/inert until then (`.is-active`, height set in JS). -->
    <div class="main-menu-bar-spacer" aria-hidden="true"></div>
</header>

<!-- Mobile off-canvas menu: the SAME main-menu re-rendered as a nested
     accordion (initOffCanvasMenu() in main.js adds the tap-to-expand
     toggles and the open/close/Escape/overlay wiring). Markup always
     present; hidden off-screen by default (`transform: translateX(-100%)`)
     and only shown below the mobile breakpoint (see main.css). -->
<div class="offcanvas-overlay" id="offcanvas-overlay" hidden></div>

<div class="offcanvas-menu" id="offcanvas-menu" aria-hidden="true">
    <div class="offcanvas-menu__header">
        <span class="offcanvas-menu__title"><?php esc_html_e('Menu', 'arena'); ?></span>
        <button type="button" class="offcanvas-menu__close" aria-label="<?php esc_attr_e('Fechar menu', 'arena'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>

    <nav class="offcanvas-nav" aria-label="<?php esc_attr_e('Menu principal (móvel)', 'arena'); ?>">
        <?php
        wp_nav_menu([
            'theme_location' => 'main-menu',
            'container'      => false,
            'menu_id'        => 'offcanvas-navigation',
            'menu_class'     => 'offcanvas-menu-list menu clearfix',
            'walker'         => new \Arena\Menus\MegaMenuWalker(),
            'fallback_cb'    => false,
        ]);
        ?>
    </nav>
</div>
