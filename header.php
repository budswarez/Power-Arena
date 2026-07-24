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

        <nav class="main-menu-container" aria-label="<?php esc_attr_e('Menu principal', 'arena'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'main-menu',
                'container'      => false,
                'menu_class'     => 'main-menu menu bsm-pure clearfix',
                'walker'         => new \Arena\Menus\MegaMenuWalker(),
                'fallback_cb'    => false,
            ]);
            ?>
        </nav>

        <?php get_template_part('template-parts/header/search'); ?>
    </div>
</header>
